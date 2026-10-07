<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

use App\Contracts\Food\BrisklySync\BrisklyCatalogGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchClassifierInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchOrchestratorInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchQueueInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncMatchRunStoreInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionRepositoryInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncSessionServiceInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenCaptureGatewayInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncTokenStoreInterface;
use App\Contracts\Food\BrisklySync\BrisklySyncVpsCatalogPortInterface;
use App\Contracts\Food\ComboCatalog\ComboCatalogPromptBuilderInterface;
use App\Contracts\Shared\CacheStoreInterface;
use App\DTO\Food\BrisklySync\BrisklySnapshotItemDto;
use App\DTO\Food\BrisklySync\BrisklySyncApplyReportDto;
use App\DTO\Food\BrisklySync\BrisklySyncApprovalsDto;
use App\DTO\Food\BrisklySync\BrisklySyncLlmCallContextDto;
use App\DTO\Food\BrisklySync\BrisklySyncSessionRecord;
use App\DTO\Food\BrisklySync\MatchLineResultDto;
use App\DTO\Food\BrisklySync\CreateBrisklySyncSessionDto;
use App\DTO\Food\BrisklySync\PriceDiffItemDto;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Food\BrisklySync\SyncResultsDto;
use App\DTO\Food\BrisklySync\VpsOnlyCreateItemDto;
use App\DTO\Food\ComboCatalog\ComboCatalogPromptDishDto;
use App\Enums\Food\BrisklySync\BrisklySyncSessionStatus;
use App\Enums\Food\ComboCatalog\ComboCatalogPromptScenario;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Exceptions\Food\FoodDomainException;

/**
 * Жизненный цикл сессии Briskly sync: snapshot → match → approvals → apply.
 */
final class BrisklySyncSessionService implements BrisklySyncSessionServiceInterface
{
    private const string APPLY_LOCK_PREFIX = 'briskly_sync:apply_lock:';

    public function __construct(
        private readonly BrisklySyncSessionRepositoryInterface $sessions,
        private readonly BrisklySyncTokenStoreInterface $tokenStore,
        private readonly BrisklySyncTokenCaptureGatewayInterface $tokenCapture,
        private readonly BrisklySyncVpsCatalogPortInterface $vpsCatalog,
        private readonly BrisklyCatalogGatewayInterface $brisklyCatalog,
        private readonly BrisklySyncMatchOrchestratorInterface $orchestrator,
        private readonly BrisklySyncMatchQueueInterface $matchQueue,
        private readonly BrisklySyncMatchRunStoreInterface $matchRuns,
        private readonly BrisklySyncMatchClassifierInterface $classifier,
        private readonly ComboCatalogPromptBuilderInterface $promptBuilder,
        private readonly CacheStoreInterface $cache,
        private readonly int $tokenTtlSeconds,
        private readonly int $sectionCap,
        private readonly float $largeDeltaRatio,
        private readonly int $applyLockTtlSeconds,
        private readonly int $matchGenerationTtlSeconds,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function createSession(CreateBrisklySyncSessionDto $dto): BrisklySyncSessionRecord
    {
        $this->vpsCatalog->assertRestaurantActive($dto->restaurantId);
        $this->vpsCatalog->assertCategoryBelongs($dto->restaurantId, $dto->vpsCategoryId);

        $token = $this->normalizeCapturedToken($this->tokenCapture->captureToken());

        $session = $this->sessions->create([
            'restaurant_id' => $dto->restaurantId,
            'created_by_max_user_id' => $dto->createdByMaxUserId,
            'vps_category_id' => $dto->vpsCategoryId,
            'search_text' => $dto->searchText,
            'clarification' => $dto->clarification,
            'status' => BrisklySyncSessionStatus::Setup,
        ]);

        $this->tokenStore->put($session->id, $token, $this->tokenTtlSeconds);

        return $session;
    }

    /**
     * {@inheritDoc}
     */
    public function getSession(string $sessionId): BrisklySyncSessionRecord
    {
        return $this->requireSession($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function sourceLines(string $sessionId): array
    {
        $session = $this->requireSession($sessionId);

        return $this->collectSourceLines($session);
    }

    /**
     * {@inheritDoc}
     */
    public function loadSnapshot(string $sessionId): BrisklySyncSessionRecord
    {
        $session = $this->requireSession($sessionId);
        $token = $this->requireToken($sessionId);

        $items = $this->brisklyCatalog->fetchSnapshot($token, $session->searchText);
        $payload = array_map(
            static fn (BrisklySnapshotItemDto $item): array => $item->toArray(),
            $items,
        );

        return $this->sessions->update($sessionId, [
            'briskly_snapshot' => $payload,
            'status' => BrisklySyncSessionStatus::Setup,
            'proposals' => null,
            'approvals' => null,
            'apply_report' => null,
            'source_price_hash' => null,
            'source_lines_snapshot' => null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function match(string $sessionId, bool $rematch = false): BrisklySyncSessionRecord
    {
        $session = $this->requireSession($sessionId);

        if ($session->status === BrisklySyncSessionStatus::Applied) {
            throw new FoodDomainException('Сессия уже применена; создайте новую.', 409);
        }

        if ($session->status === BrisklySyncSessionStatus::Matching) {
            throw new FoodDomainException('Match уже выполняется для этой сессии.', 409);
        }

        if ($session->status === BrisklySyncSessionStatus::Matched && ! $rematch) {
            throw new FoodDomainException('Повторный match требует явного rematch=true.', 422);
        }

        if ($session->brisklySnapshot === null) {
            throw new FoodDomainException('Сначала загрузите snapshot Briskly.', 422);
        }

        $this->vpsCatalog->assertRestaurantActive($session->restaurantId);

        $queued = $this->sessions->update($sessionId, [
            'status' => BrisklySyncSessionStatus::Matching,
            'proposals' => null,
            'approvals' => null,
            'apply_report' => null,
        ]);

        $this->matchQueue->dispatch($sessionId);

        $fresh = $this->sessions->findById($sessionId);

        return $fresh ?? $queued;
    }

    /**
     * {@inheritDoc}
     */
    public function performQueuedMatch(string $sessionId): void
    {
        $session = $this->sessions->findById($sessionId);
        if ($session === null || $session->status !== BrisklySyncSessionStatus::Matching) {
            return;
        }

        try {
            $this->runMatchHandshake($sessionId);
        } catch (FoodDomainException) {
            $this->failQueuedMatch($sessionId);

            return;
        } catch (\Throwable $exception) {
            $this->failQueuedMatch($sessionId);
            throw $exception;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeQueuedMatch(
        string $sessionId,
        string $matchGeneration,
        ?array $matchLines,
        ?string $error = null,
    ): void {
        if (! $this->matchRuns->matches($sessionId, $matchGeneration)) {
            throw new FoodDomainException('Устаревший callback match.', 409);
        }

        $session = $this->sessions->findById($sessionId);
        if ($session === null || $session->status !== BrisklySyncSessionStatus::Matching) {
            throw new FoodDomainException('Устаревший callback match.', 409);
        }

        $errorText = is_string($error) ? trim($error) : '';
        $hasError = $errorText !== '';
        $hasLines = $matchLines !== null;
        if ($hasError === $hasLines) {
            throw new FoodDomainException('Нужны либо match_lines, либо error.', 422);
        }

        if ($hasError) {
            $this->failQueuedMatch($sessionId);

            return;
        }

        $sourceRaw = $session->sourceLinesSnapshot;
        if (! is_array($sourceRaw) || $session->brisklySnapshot === null) {
            throw new FoodDomainException('Устаревший callback match.', 409);
        }

        $sourceLines = [];
        foreach ($sourceRaw as $row) {
            if (is_array($row)) {
                $sourceLines[] = SourceMenuLineDto::fromArray($row);
            }
        }

        $snapshot = array_map(
            static fn (array $row): BrisklySnapshotItemDto => BrisklySnapshotItemDto::fromArray($row),
            $session->brisklySnapshot,
        );

        $this->persistMatched($sessionId, $sourceLines, $snapshot, $matchLines);
        $this->matchRuns->forget($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function expireQueuedMatch(string $sessionId, string $matchGeneration): void
    {
        if (! $this->matchRuns->matches($sessionId, $matchGeneration)) {
            return;
        }

        $session = $this->sessions->findById($sessionId);
        if ($session === null || $session->status !== BrisklySyncSessionStatus::Matching) {
            return;
        }

        $this->failQueuedMatch($sessionId);
    }

    /**
     * {@inheritDoc}
     */
    public function failQueuedMatch(string $sessionId): void
    {
        $generation = $this->matchRuns->get($sessionId);
        $this->sessions->markMatchingAsFailed($sessionId);
        if ($generation !== null) {
            $this->orchestrator->abort($sessionId, $generation);
        }
        $this->matchRuns->forget($sessionId);
    }

    /**
     * Handshake sidecar + expire job; вызывается только из очереди при status=matching.
     */
    private function runMatchHandshake(string $sessionId): void
    {
        $session = $this->requireSession($sessionId);

        if ($session->brisklySnapshot === null) {
            throw new FoodDomainException('Сначала загрузите snapshot Briskly.', 422);
        }

        $this->vpsCatalog->assertRestaurantActive($session->restaurantId);

        $sourceLines = $this->collectSourceLines($session);
        $snapshot = array_map(
            static fn (array $row): BrisklySnapshotItemDto => BrisklySnapshotItemDto::fromArray($row),
            $session->brisklySnapshot,
        );

        // Пустой Briskly → все VPS-lines идут в CREATE (без LLM).
        // Пустой source → нечего матчить (без LLM).
        // Правила исключения/нормализации из clarification обрабатывает LLM (prompt NamingRules).
        if ($snapshot === [] || $sourceLines === []) {
            $this->persistMatched($sessionId, $sourceLines, $snapshot, []);

            return;
        }

        $previous = $this->matchRuns->get($sessionId);
        if ($previous !== null) {
            $this->orchestrator->abort($sessionId, $previous);
        }

        $generation = $this->matchRuns->allocate($sessionId, $this->matchGenerationTtlSeconds);

        $this->sessions->update($sessionId, [
            'source_lines_snapshot' => array_map(
                static fn (SourceMenuLineDto $line): array => $line->toArray(),
                $sourceLines,
            ),
            'source_price_hash' => BrisklySyncPrice::hashSourcePrices($sourceLines),
        ]);

        $promptDishes = [];
        foreach ($sourceLines as $index => $line) {
            $promptDishes[] = new ComboCatalogPromptDishDto(
                id: $this->promptDishId($line, $index),
                name: $line->displayName,
                price: BrisklySyncPrice::normalize($line->price),
                weightLabel: null,
                lineKey: $line->lineKey,
            );
        }

        $brisklyForPrompt = array_map(
            static fn (BrisklySnapshotItemDto $item): array => [
                'id' => $item->id,
                'name' => $item->name,
            ],
            $snapshot,
        );

        $prompt = $this->promptBuilder->build(
            ComboCatalogPromptScenario::MatchNames,
            $session->restaurantId,
            $session->clarification,
            $promptDishes,
            $brisklyForPrompt,
        );

        $this->orchestrator->start(
            $prompt,
            $sourceLines,
            $snapshot,
            $sessionId,
            $generation,
            new BrisklySyncLlmCallContextDto(
                sessionId: $sessionId,
                restaurantId: $session->restaurantId,
                createdByMaxUserId: $session->createdByMaxUserId,
            ),
        );

        $this->matchQueue->dispatchExpire($sessionId, $generation);
    }

    /**
     * @param  list<SourceMenuLineDto>  $sourceLines
     * @param  list<BrisklySnapshotItemDto>  $snapshot
     * @param  list<MatchLineResultDto>  $matchLines
     */
    private function persistMatched(
        string $sessionId,
        array $sourceLines,
        array $snapshot,
        array $matchLines,
    ): void {
        $syncResults = $this->classifier->classify(
            $sourceLines,
            $snapshot,
            $matchLines,
            $this->sectionCap,
        );

        $proposals = [
            'match_lines' => array_map(
                static fn (MatchLineResultDto $line): array => $line->toArray(),
                $matchLines,
            ),
            'sync_results' => $syncResults->toArray(),
        ];

        $this->sessions->update($sessionId, [
            'source_lines_snapshot' => array_map(
                static fn (SourceMenuLineDto $line): array => $line->toArray(),
                $sourceLines,
            ),
            'source_price_hash' => BrisklySyncPrice::hashSourcePrices($sourceLines),
            'proposals' => $proposals,
            'approvals' => null,
            'status' => BrisklySyncSessionStatus::Matched,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function syncResults(string $sessionId): SyncResultsDto
    {
        $session = $this->requireSession($sessionId);
        if ($session->proposals === null || ! isset($session->proposals['sync_results'])) {
            throw new FoodDomainException('Результаты match ещё не готовы.', 422);
        }

        /** @var array<string, mixed> $raw */
        $raw = $session->proposals['sync_results'];

        return SyncResultsDto::fromArray($raw);
    }

    /**
     * {@inheritDoc}
     */
    public function updateApprovals(string $sessionId, BrisklySyncApprovalsDto $approvals): BrisklySyncSessionRecord
    {
        $session = $this->requireSession($sessionId);
        if ($session->status === BrisklySyncSessionStatus::Applied) {
            throw new FoodDomainException('Сессия уже применена.', 409);
        }

        if ($session->status === BrisklySyncSessionStatus::Matching) {
            throw new FoodDomainException('Match ещё выполняется для этой сессии.', 409);
        }

        $results = $this->syncResults($sessionId);
        $this->assertApprovalsValid($approvals, $results, $session->allowedBrisklyCategoryIds);

        $hasApply = false;
        foreach ($approvals->priceUpdates as $item) {
            if ($item->apply) {
                $hasApply = true;
                break;
            }
        }
        if (! $hasApply) {
            foreach ($approvals->creates as $item) {
                if ($item->apply) {
                    $hasApply = true;
                    break;
                }
            }
        }

        return $this->sessions->update($sessionId, [
            'approvals' => $approvals->toArray(),
            'status' => $hasApply
                ? BrisklySyncSessionStatus::Approved
                : BrisklySyncSessionStatus::Matched,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $sessionId): BrisklySyncApplyReportDto
    {
        $session = $this->requireSession($sessionId);

        if ($session->status === BrisklySyncSessionStatus::Applied) {
            throw new FoodDomainException('Повторный apply запрещён для этой сессии.', 409);
        }

        if ($session->status !== BrisklySyncSessionStatus::Approved) {
            throw new FoodDomainException('Сначала сохраните approvals со статусом approved.', 422);
        }

        if ($session->approvals === null) {
            throw new FoodDomainException('Approvals отсутствуют.', 422);
        }

        $this->vpsCatalog->assertRestaurantActive($session->restaurantId);

        $token = $this->requireToken($sessionId);
        $lockKey = self::APPLY_LOCK_PREFIX.$sessionId;
        if ($this->cache->get($lockKey) !== null) {
            throw new FoodDomainException('Apply уже выполняется для этой сессии.', 409);
        }
        $this->cache->put($lockKey, 1, $this->applyLockTtlSeconds);

        try {
            $freshLines = $this->collectSourceLines($session);
            $freshHash = BrisklySyncPrice::hashSourcePrices($freshLines);
            if ($session->sourcePriceHash !== null && $session->sourcePriceHash !== $freshHash) {
                throw new FoodDomainException(
                    'Цены source изменились с момента match — выполните rematch.',
                    409,
                );
            }

            $results = $this->syncResults($sessionId);
            $approvals = BrisklySyncApprovalsDto::fromArray($session->approvals);
            $this->assertApprovalsValid($approvals, $results, $session->allowedBrisklyCategoryIds);

            $snapshotIds = [];
            foreach ($session->brisklySnapshot ?? [] as $row) {
                if (is_array($row)) {
                    $snapshotIds[(int) ($row['id'] ?? 0)] = true;
                }
            }

            $priceByKey = [];
            foreach ($results->priceUpdates->items as $item) {
                $priceByKey[$item->lineKey] = $item;
            }
            $createByKey = [];
            foreach ($results->creates->items as $item) {
                $createByKey[$item->lineKey] = $item;
            }

            $categoryCatalog = $this->categoryCatalogMap($sessionId, $token, $session);

            // Preflight до любой записи в Briskly: snapshot, конфликты id, Δ>50%, категории.
            $this->assertApplyPreflight(
                $approvals,
                $priceByKey,
                $createByKey,
                $snapshotIds,
                $categoryCatalog,
            );

            $report = [
                'updated' => 0,
                'created' => 0,
                'skipped_unchecked' => 0,
                'skipped_equal' => 0,
                'errors' => [],
                'created_items' => [],
            ];
            /** @var list<array{id: int, name: string, price: string}> $snapshotAdditions */
            $snapshotAdditions = [];

            $updateCount = 0;
            foreach ($approvals->priceUpdates as $approval) {
                if (! $approval->apply) {
                    $report['skipped_unchecked']++;

                    continue;
                }
                if ($updateCount >= $this->sectionCap) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => 'Превышен лимит UPDATE '.$this->sectionCap,
                    ];

                    continue;
                }

                $proposal = $priceByKey[$approval->lineKey] ?? null;
                if (! $proposal instanceof PriceDiffItemDto) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => 'line_key отсутствует в price_updates',
                    ];

                    continue;
                }

                if (BrisklySyncPrice::equal($proposal->sourcePrice, $proposal->brisklyPrice)) {
                    $report['skipped_equal']++;

                    continue;
                }

                try {
                    $this->brisklyCatalog->updateItemPrice(
                        $token,
                        $proposal->brisklyItemId,
                        $proposal->sourcePrice,
                    );
                    $report['updated']++;
                    $updateCount++;
                } catch (FoodDomainException $exception) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $createCount = 0;
            foreach ($approvals->creates as $approval) {
                if (! $approval->apply) {
                    $report['skipped_unchecked']++;

                    continue;
                }
                if ($createCount >= $this->sectionCap) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => 'Превышен лимит CREATE '.$this->sectionCap,
                    ];

                    continue;
                }

                $proposal = $createByKey[$approval->lineKey] ?? null;
                if (! $proposal instanceof VpsOnlyCreateItemDto) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => 'line_key отсутствует в creates',
                    ];

                    continue;
                }

                $categoryId = $approval->brisklyCategoryId;
                if ($categoryId === null) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => 'briskly_category_id обязателен для CREATE',
                    ];

                    continue;
                }

                $catalogId = $categoryCatalog[$categoryId] ?? null;
                if ($catalogId === null) {
                    throw new FoodDomainException(
                        'briskly_category_id не из списка категорий сессии.',
                        422,
                    );
                }

                try {
                    $created = $this->brisklyCatalog->createItem(
                        $token,
                        $proposal->createName(),
                        $proposal->sourcePrice,
                        $categoryId,
                        $catalogId,
                    );
                    $report['created']++;
                    $createCount++;
                    $report['created_items'][] = [
                        'line_key' => $approval->lineKey,
                        'briskly_item_id' => $created->id,
                        'barcode' => $created->barcode,
                        'name' => $created->name,
                    ];
                    $snapshotAdditions[] = (new BrisklySnapshotItemDto(
                        id: $created->id,
                        name: $created->name,
                        price: $created->price,
                    ))->toArray();
                } catch (FoodDomainException $exception) {
                    $report['errors'][] = [
                        'line_key' => $approval->lineKey,
                        'message' => $exception->getMessage(),
                    ];
                }
            }

            $applyReport = BrisklySyncApplyReportDto::fromArray($report);

            $sessionUpdate = [
                'apply_report' => $applyReport->toArray(),
                'status' => BrisklySyncSessionStatus::Applied,
            ];
            if ($snapshotAdditions !== []) {
                $existingSnapshot = is_array($session->brisklySnapshot)
                    ? $session->brisklySnapshot
                    : [];
                $sessionUpdate['briskly_snapshot'] = array_values([
                    ...$existingSnapshot,
                    ...$snapshotAdditions,
                ]);
            }

            $this->sessions->update($sessionId, $sessionUpdate);
            $this->tokenStore->forget($sessionId);

            return $applyReport;
        } finally {
            $this->cache->forget($lockKey);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function listBrisklyCategories(string $sessionId): array
    {
        $session = $this->requireSession($sessionId);
        $token = $this->requireToken($sessionId);

        $categories = $this->brisklyCatalog->listCategories($token);
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = $category->id;
        }

        $this->sessions->update($sessionId, [
            'allowed_briskly_category_ids' => $ids,
        ]);

        return $categories;
    }

    /**
     * @return list<SourceMenuLineDto>
     */
    private function collectSourceLines(BrisklySyncSessionRecord $session): array
    {
        return $this->vpsCatalog->collectSourceLines(
            $session->restaurantId,
            $session->vpsCategoryId,
            $session->searchText,
        );
    }

    private function requireSession(string $sessionId): BrisklySyncSessionRecord
    {
        $session = $this->sessions->findById($sessionId);
        if ($session === null) {
            throw new FoodDomainException('Сессия не найдена.', 404);
        }

        return $session;
    }

    private function requireToken(string $sessionId): string
    {
        $token = $this->tokenStore->get($sessionId);
        if ($token === null) {
            throw new FoodDomainException(
                'Токен Briskly недоступен — создайте сессию заново.',
                422,
            );
        }

        return $token;
    }

    /**
     * Нормализация и проверка длины JWT после CDP capture.
     *
     * @return non-empty-string
     */
    private function normalizeCapturedToken(string $raw): string
    {
        $token = BrisklySyncBearerToken::normalize($raw);
        if ($token === '' || strlen($token) < 10 || strlen($token) > 4096) {
            throw new FoodDomainException(
                'Не удалось получить токен Briskly: некорректная длина токена.',
                422,
            );
        }

        if (BrisklySyncBearerToken::isExpired($token)) {
            throw new FoodDomainException(
                'Токен Briskly истёк. Обновите вкладку кабинета (вход) и повторите поиск.',
                422,
            );
        }

        return $token;
    }

    /**
     * Preflight apply: snapshot / conflict / large delta / category — до записи в Briskly.
     *
     * @param  array<string, PriceDiffItemDto>  $priceByKey
     * @param  array<string, VpsOnlyCreateItemDto>  $createByKey
     * @param  array<int, true>  $snapshotIds
     * @param  array<int, int>  $categoryCatalog
     */
    private function assertApplyPreflight(
        BrisklySyncApprovalsDto $approvals,
        array $priceByKey,
        array $createByKey,
        array $snapshotIds,
        array $categoryCatalog,
    ): void {
        $seenBrisklyItemIds = [];
        foreach ($approvals->priceUpdates as $approval) {
            if (! $approval->apply) {
                continue;
            }

            $proposal = $priceByKey[$approval->lineKey] ?? null;
            if (! $proposal instanceof PriceDiffItemDto) {
                throw new FoodDomainException(
                    'price_updates line_key отсутствует в sync-results: '.$approval->lineKey,
                    422,
                );
            }

            if (! isset($snapshotIds[$proposal->brisklyItemId])) {
                throw new FoodDomainException(
                    'briskly_item_id вне snapshot сессии: '.$proposal->brisklyItemId,
                    422,
                );
            }

            if (isset($seenBrisklyItemIds[$proposal->brisklyItemId])) {
                throw new FoodDomainException(
                    'Конфликт: два approvals на один briskly_item_id.',
                    422,
                );
            }
            $seenBrisklyItemIds[$proposal->brisklyItemId] = true;

            if (BrisklySyncPrice::equal($proposal->sourcePrice, $proposal->brisklyPrice)) {
                continue;
            }

            $delta = BrisklySyncPrice::relativeDelta($proposal->sourcePrice, $proposal->brisklyPrice);
            if ($delta > $this->largeDeltaRatio && ! $approval->confirmLargeDelta) {
                throw new FoodDomainException(
                    'Требуется confirm_large_delta для Δ цены > 50%.',
                    422,
                );
            }
        }

        foreach ($approvals->creates as $approval) {
            if (! $approval->apply) {
                continue;
            }

            $proposal = $createByKey[$approval->lineKey] ?? null;
            if (! $proposal instanceof VpsOnlyCreateItemDto) {
                throw new FoodDomainException(
                    'creates line_key отсутствует в sync-results: '.$approval->lineKey,
                    422,
                );
            }

            $categoryId = $approval->brisklyCategoryId;
            if ($categoryId === null) {
                throw new FoodDomainException(
                    'briskly_category_id обязателен для CREATE.',
                    422,
                );
            }

            if (! isset($categoryCatalog[$categoryId])) {
                throw new FoodDomainException(
                    'briskly_category_id не из списка категорий сессии.',
                    422,
                );
            }
        }
    }

    /**
     * @param  list<int>|null  $allowedCategoryIds
     */
    private function assertApprovalsValid(
        BrisklySyncApprovalsDto $approvals,
        SyncResultsDto $results,
        ?array $allowedCategoryIds,
    ): void {
        if (count($approvals->priceUpdates) > $this->sectionCap) {
            throw new FoodDomainException('price_updates: максимум '.$this->sectionCap.' строк.', 422);
        }
        if (count($approvals->creates) > $this->sectionCap) {
            throw new FoodDomainException('creates: максимум '.$this->sectionCap.' строк.', 422);
        }

        $priceKeys = [];
        foreach ($results->priceUpdates->items as $item) {
            $priceKeys[$item->lineKey] = true;
        }
        $createKeys = [];
        foreach ($results->creates->items as $item) {
            $createKeys[$item->lineKey] = true;
        }

        $seenPriceKeys = [];
        foreach ($approvals->priceUpdates as $item) {
            if (! isset($priceKeys[$item->lineKey])) {
                throw new FoodDomainException(
                    'price_updates line_key отсутствует в sync-results: '.$item->lineKey,
                    422,
                );
            }
            if (isset($seenPriceKeys[$item->lineKey])) {
                throw new FoodDomainException('Дублирующий line_key в price_updates.', 422);
            }
            $seenPriceKeys[$item->lineKey] = true;
        }

        $seenCreateKeys = [];
        foreach ($approvals->creates as $item) {
            if (! isset($createKeys[$item->lineKey])) {
                throw new FoodDomainException(
                    'creates line_key отсутствует в sync-results: '.$item->lineKey,
                    422,
                );
            }
            if (isset($seenCreateKeys[$item->lineKey])) {
                throw new FoodDomainException('Дублирующий line_key в creates.', 422);
            }
            $seenCreateKeys[$item->lineKey] = true;

            if ($item->apply && $item->brisklyCategoryId === null) {
                throw new FoodDomainException(
                    'briskly_category_id обязателен для отмеченного CREATE.',
                    422,
                );
            }

            if (
                $item->apply
                && $item->brisklyCategoryId !== null
                && $allowedCategoryIds !== null
                && ! in_array($item->brisklyCategoryId, $allowedCategoryIds, true)
            ) {
                throw new FoodDomainException(
                    'briskly_category_id не из списка категорий сессии.',
                    422,
                );
            }
        }
    }

    /**
     * @return array<int, int> categoryId => catalogId
     */
    private function categoryCatalogMap(
        string $sessionId,
        string $token,
        BrisklySyncSessionRecord $session,
    ): array {
        $categories = $this->brisklyCatalog->listCategories($token);
        $map = [];
        $ids = [];
        foreach ($categories as $category) {
            $ids[] = $category->id;
            if ($category->catalogId !== null) {
                $map[$category->id] = $category->catalogId;
            }
        }

        if ($session->allowedBrisklyCategoryIds === null) {
            $this->sessions->update($sessionId, [
                'allowed_briskly_category_ids' => $ids,
            ]);
        }

        return $map;
    }

    private function promptDishId(SourceMenuLineDto $line, int $index): int
    {
        if ($line->type === DailyMenuLineType::Single && $line->partDishIds !== []) {
            return (int) $line->partDishIds[0];
        }

        $hash = crc32($line->lineKey);

        return $hash > 0 ? $hash : ($index + 1_000_000);
    }
}
