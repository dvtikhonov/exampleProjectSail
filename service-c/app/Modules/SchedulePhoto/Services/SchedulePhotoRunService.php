<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Services;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasDictionaryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesReaderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoAliasesWriterInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoCatalogScopeBuilderInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrOrchestratorInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoOcrQueueInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoStoreInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunRepositoryInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunServiceInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrStartDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoReadinessResultDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoResolveDiscardedDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Enums\SchedulePhotoRunStatus;
use DateTimeImmutable;
use Throwable;

/**
 * Оркестрация прогонов SchedulePhoto: readiness → локальный OCR → match/apply на BASE_URL.
 *
 * Таблица прогонов, фото и sidecar остаются локальными.
 * Каталог, сверка и запись графика идут на origin прогона через PhotoText.
 */
final class SchedulePhotoRunService implements SchedulePhotoRunServiceInterface
{
    public function __construct(
        private readonly SchedulePhotoReadinessCheckerInterface $readiness,
        private readonly SchedulePhotoRunRepositoryInterface $runs,
        private readonly SchedulePhotoOcrQueueInterface $ocrQueue,
        private readonly SchedulePhotoOcrOrchestratorInterface $ocrOrchestrator,
        private readonly SchedulePhotoPhotoStoreInterface $photoStore,
        private readonly SchedulePhotoAliasesReaderInterface $aliasesReader,
        private readonly SchedulePhotoAliasDictionaryInterface $aliasDictionary,
        private readonly SchedulePhotoAliasesWriterInterface $aliasesWriter,
        private readonly SchedulePhotoCatalogScopeBuilderInterface $catalogScope,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function start(SchedulePhotoStartRunDto $dto): SchedulePhotoRunRecord
    {
        $ready = $this->readiness->check($dto->baseUrl);
        if (! $ready->ok) {
            throw new FoodDomainException($this->readinessFailureMessage($ready), 422);
        }

        $dateFrom = $dto->dateFrom;
        $dateTo = $this->dateToFrom($dateFrom);
        $runId = $this->newUuid();

        // Scope каталога выбранного origin — до постановки job (fail-fast без sidecar).
        $this->catalogScope->build($dto->baseUrl, $dto->restaurantId, $dto->categoryIds);

        $photoPath = $this->photoStore->storeFromLocalPath($runId, $dto->photoAbsolutePath);

        $run = $this->runs->create([
            'id' => $runId,
            'status' => SchedulePhotoRunStatus::Pending,
            'base_url' => $dto->baseUrl,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'restaurant_id' => $dto->restaurantId,
            'category_ids' => $dto->categoryIds,
            'created_by_max_user_id' => $dto->createdByMaxUserId,
            'photo_path' => $photoPath,
            'discarded' => [],
        ]);

        $this->ocrQueue->dispatch($runId);

        return $this->runs->findById($runId) ?? $run;
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $runId): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($runId);
        if ($run === null) {
            throw new FoodDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        return $run;
    }

    /**
     * {@inheritDoc}
     */
    public function getForAdmin(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        return $this->findOwnedOrFail($runId, $actorMaxUserId);
    }

    /**
     * Прогон только создателя. Чужой и отсутствующий дают одну и ту же 404,
     * чтобы UUID не подтверждался разным текстом ошибки.
     *
     * @throws FoodDomainException
     */
    private function findOwnedOrFail(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($runId);
        if ($run === null || $run->createdByMaxUserId !== $actorMaxUserId) {
            throw new FoodDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        return $run;
    }

    /**
     * {@inheritDoc}
     */
    public function performQueuedOcr(string $runId): void
    {
        $run = $this->runs->findById($runId);
        if ($run === null || $run->status !== SchedulePhotoRunStatus::Pending) {
            return;
        }

        $this->runs->update($runId, [
            'status' => SchedulePhotoRunStatus::OcrRunning,
            'error_message' => null,
        ]);

        try {
            $this->runOcrHandshake($runId);
        } catch (FoodDomainException $exception) {
            $this->failQueuedOcr($runId, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->failQueuedOcr($runId, $exception->getMessage());
            throw $exception;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function completeOcr(SchedulePhotoOcrCallbackDto $dto): SchedulePhotoRunRecord
    {
        $run = $this->runs->findById($dto->runId);
        if ($run === null) {
            throw new FoodDomainException('Прогон SchedulePhoto не найден.', 404);
        }

        if ($run->status !== SchedulePhotoRunStatus::OcrRunning) {
            throw new FoodDomainException('Устаревший callback OCR.', 409);
        }

        $errorText = is_string($dto->error) ? trim($dto->error) : '';
        $hasError = $errorText !== '';
        $hasEntries = $dto->entries !== null;

        if ($hasError === $hasEntries) {
            throw new FoodDomainException('Нужны либо entries, либо error.', 422);
        }

        if ($hasError) {
            return $this->runs->update($dto->runId, [
                'status' => SchedulePhotoRunStatus::Failed,
                'error_message' => mb_substr($errorText, 0, 2000),
                'discarded' => $dto->discarded,
            ]);
        }

        /** @var list<array{raw_title: string, dates: list<string>}> $entries */
        $entries = $dto->entries ?? [];

        $matchEntries = [];
        foreach ($entries as $row) {
            $name = trim($row['raw_title']);
            if ($name === '') {
                continue;
            }
            $matchEntries[] = new PhotoTextScheduleEntryDto(
                name: $name,
                dates: $row['dates'],
            );
        }

        $result = $this->photoText->match(
            $run->baseUrl,
            $run->restaurantId,
            $run->categoryIds,
            $run->dateFrom,
            $run->dateTo,
            $matchEntries,
        );

        return $this->runs->update($dto->runId, [
            'status' => SchedulePhotoRunStatus::Matched,
            'match_result' => $result->toArray(),
            'discarded' => $dto->discarded,
            'error_message' => null,
        ]);
    }

    /**
     * {@inheritDoc}
     *
     * Human-confirm = сам вызов apply (POST). Пишет график на base_url прогона;
     * при matched_count = 0 — STOP без записи. Статус applied — только после ответа PhotoText.
     * До записи — только прогон создателя.
     */
    public function apply(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        $run = $this->findOwnedOrFail($runId, $actorMaxUserId);

        if ($run->status === SchedulePhotoRunStatus::Applied) {
            throw new FoodDomainException('График этого прогона уже записан.', 409);
        }

        if ($run->status !== SchedulePhotoRunStatus::Matched) {
            throw new FoodDomainException(
                'Запись графика доступна только после успешного match (status=matched).',
                409,
            );
        }

        $matchResult = $run->matchResult;
        if (! is_array($matchResult)) {
            throw new FoodDomainException('У прогона нет результата match.', 422);
        }

        $matchedCount = (int) ($matchResult['matched_count'] ?? 0);
        $matchedRows = $matchResult['matched'] ?? [];
        if ($matchedCount <= 0 || ! is_array($matchedRows) || $matchedRows === []) {
            throw new FoodDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        $entries = $this->entriesFromStoredMatchResult($matchResult);
        if ($entries === []) {
            throw new FoodDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        $applied = $this->photoText->apply(
            $run->baseUrl,
            $run->restaurantId,
            $run->categoryIds,
            $run->dateFrom,
            $run->dateTo,
            $entries,
        );

        if (! $applied->applied || $applied->matchedCount === 0) {
            throw new FoodDomainException(
                'Нет matched позиций: запись графика остановлена (matched_count = 0).',
                422,
            );
        }

        return $this->runs->update($runId, [
            'status' => SchedulePhotoRunStatus::Applied,
            'match_result' => $applied->toArray(),
            'error_message' => null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveDiscarded(SchedulePhotoResolveDiscardedDto $dto, int $actorMaxUserId): SchedulePhotoRunRecord
    {
        $run = $this->findOwnedOrFail($dto->runId, $actorMaxUserId);

        if ($run->status !== SchedulePhotoRunStatus::Matched) {
            throw new FoodDomainException(
                'Разрешить отброшенную строку можно только при status=matched.',
                409,
            );
        }

        $rawTitle = trim($dto->rawTitle);
        if ($rawTitle === '') {
            throw new FoodDomainException('Укажите raw_title отброшенной строки.', 422);
        }

        $discardedIndex = null;
        foreach ($run->discarded as $index => $row) {
            if (trim((string) ($row['raw_title'] ?? '')) === $rawTitle) {
                $discardedIndex = $index;
                break;
            }
        }

        if ($discardedIndex === null) {
            throw new FoodDomainException('Строка не найдена среди отброшенных.', 422);
        }

        $dishName = $dto->dishName !== null ? trim($dto->dishName) : '';
        if ($dishName === '') {
            $fromAlias = $this->aliasDictionary->resolveCanon($rawTitle);
            $dishName = $fromAlias !== null ? trim($fromAlias) : '';
        }

        if ($dishName === '') {
            throw new FoodDomainException(
                'Нет канона: укажите dish_name или добавьте алиас в словарь.',
                422,
            );
        }

        $dates = $this->datesForResolvedDiscarded(
            $run->dateFrom,
            $run->dateTo,
            $run->discarded[$discardedIndex],
            $dto->dates,
        );
        $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        $canonInScope = $this->findScopeDishName($scope, $dishName);
        if ($canonInScope === null) {
            throw new FoodDomainException(
                'Блюдо «'.$dishName.'» нет в выбранных категориях каталога.',
                422,
            );
        }

        // Ручной выбор из селектора → upsert OCR→канон (в т.ч. замена алиаса вне категорий).
        if ($dto->dishName !== null && trim($dto->dishName) !== '') {
            try {
                $this->aliasesWriter->upsertPair($rawTitle, $canonInScope);
            } catch (Throwable $e) {
                throw new FoodDomainException(
                    'Не удалось сохранить пару в phototext-dish-aliases.md: '.$e->getMessage(),
                    500,
                );
            }
        }

        $matchResult = is_array($run->matchResult) ? $run->matchResult : [];
        $entries = $this->entriesFromStoredMatchResult($matchResult);
        $entries[] = new PhotoTextScheduleEntryDto(name: $canonInScope, dates: $dates);

        $result = $this->photoText->match(
            $run->baseUrl,
            $run->restaurantId,
            $run->categoryIds,
            $run->dateFrom,
            $run->dateTo,
            $entries,
        );

        $remainingDiscarded = [];
        foreach ($run->discarded as $index => $row) {
            if ($index === $discardedIndex) {
                continue;
            }
            $remainingDiscarded[] = $row;
        }

        return $this->runs->update($dto->runId, [
            'status' => SchedulePhotoRunStatus::Matched,
            'match_result' => $result->toArray(),
            'discarded' => $remainingDiscarded,
            'error_message' => null,
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function toAdminArray(SchedulePhotoRunRecord $run): array
    {
        $payload = $run->toArray();
        $scope = [];

        try {
            $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        } catch (FoodDomainException) {
            $scope = [];
        }

        $payload['discarded'] = $this->enrichDiscarded($run->discarded, $scope);
        $payload['catalog_scope'] = array_values(array_map(
            static fn (array $dish): array => [
                'id' => (int) $dish['id'],
                'name' => (string) $dish['name'],
                'category_id' => (int) $dish['category_id'],
                'category_name' => (string) ($dish['category_name'] ?? ''),
            ],
            $scope,
        ));

        return $payload;
    }

    /**
     * @param  list<array{raw_title: string, reason: string, dates?: list<string>}>  $discarded
     * @param  list<array{id: int, name: string, category_id: int, category_name?: string}>  $scope
     * @return list<array{
     *     raw_title: string,
     *     reason: string,
     *     dates: list<string>,
     *     suggested_canon: string|null,
     *     suggested_in_scope: bool,
     *     alias_missing: bool
     * }>
     */
    private function enrichDiscarded(array $discarded, array $scope): array
    {
        $enriched = [];

        foreach ($discarded as $row) {
            $rawTitle = trim((string) ($row['raw_title'] ?? ''));
            $reason = (string) ($row['reason'] ?? '');
            $suggested = $rawTitle !== ''
                ? $this->aliasDictionary->resolveCanon($rawTitle)
                : null;
            $inScope = false;
            if ($suggested !== null) {
                $scopeName = $this->findScopeDishName($scope, $suggested);
                if ($scopeName !== null) {
                    $suggested = $scopeName;
                    $inScope = true;
                }
            }

            $enriched[] = [
                'raw_title' => $rawTitle,
                'reason' => $reason,
                'dates' => $this->normalizeEntryDates($row['dates'] ?? null),
                'suggested_canon' => $suggested,
                'suggested_in_scope' => $inScope,
                'alias_missing' => $suggested === null,
            ];
        }

        return $enriched;
    }

    /**
     * @param  list<array{id: int, name: string, category_id: int, category_name?: string}>  $scope
     */
    private function findScopeDishName(array $scope, string $dishName): ?string
    {
        $needle = $this->aliasDictionary->normalizeKey($dishName);
        if ($needle === '') {
            return null;
        }

        foreach ($scope as $dish) {
            $name = (string) ($dish['name'] ?? '');
            if ($this->aliasDictionary->normalizeKey($name) === $needle) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Даты resolve: дни с фото на discarded, если OCR их сохранил.
     * Иначе — даты из запроса (ручной выбор дня). Все дни окна сами не подставляются.
     *
     * @param  array{raw_title?: string, reason?: string, dates?: list<string>}  $discardedRow
     * @param  list<string>  $requestedDates
     * @return list<string>
     */
    private function datesForResolvedDiscarded(
        string $dateFrom,
        string $dateTo,
        array $discardedRow,
        array $requestedDates,
    ): array {
        $photoDates = $this->normalizeEntryDates($discardedRow['dates'] ?? null);
        if ($photoDates === []) {
            $requested = $this->assertDatesInWindow($dateFrom, $dateTo, $requestedDates);
            if ($this->isEntireWindow($dateFrom, $dateTo, $requested)) {
                throw new FoodDomainException(
                    'У строки нет даты с фото. Укажите день заголовка группы, не все дни окна.',
                    422,
                );
            }

            return $requested;
        }

        $insideWindow = [];
        foreach ($photoDates as $date) {
            if ($date >= $dateFrom && $date <= $dateTo) {
                $insideWindow[] = $date;
            }
        }

        if ($insideWindow === []) {
            throw new FoodDomainException(
                'Дата с фото вне окна '.$dateFrom.'…'.$dateTo.'.',
                422,
            );
        }

        return array_values(array_unique($insideWindow));
    }

    /**
     * @param  list<string>  $dates
     */
    private function isEntireWindow(string $dateFrom, string $dateTo, array $dates): bool
    {
        $expected = [];
        $cursor = new DateTimeImmutable($dateFrom);
        $end = new DateTimeImmutable($dateTo);

        while ($cursor <= $end) {
            $expected[] = $cursor->format('Y-m-d');
            $cursor = $cursor->modify('+1 day');
        }

        $actual = $dates;
        sort($actual);

        return $actual === $expected;
    }

    /**
     * @param  list<string>  $dates
     * @return list<string>
     */
    private function assertDatesInWindow(string $dateFrom, string $dateTo, array $dates): array
    {
        if ($dates === []) {
            throw new FoodDomainException('Укажите хотя бы одну дату в окне графика.', 422);
        }

        $normalized = [];
        foreach ($dates as $date) {
            $trimmed = trim($date);
            if ($trimmed === '') {
                continue;
            }
            if (
                DateTimeImmutable::createFromFormat('!Y-m-d', $trimmed) === false
                || $trimmed < $dateFrom
                || $trimmed > $dateTo
            ) {
                throw new FoodDomainException(
                    'Дата '.$trimmed.' вне окна '.$dateFrom.'…'.$dateTo.'.',
                    422,
                );
            }
            $normalized[] = $trimmed;
        }

        $normalized = array_values(array_unique($normalized));
        if ($normalized === []) {
            throw new FoodDomainException('Укажите хотя бы одну дату в окне графика.', 422);
        }

        return $normalized;
    }

    /**
     * Восстанавливает entries для schedule/apply из сохранённого отчёта match
     * (matched + issues), чтобы после apply в отчёте остались issues.
     *
     * @param  array<string, mixed>  $matchResult
     * @return list<PhotoTextScheduleEntryDto>
     */
    private function entriesFromStoredMatchResult(array $matchResult): array
    {
        $entries = [];

        $matched = $matchResult['matched'] ?? [];
        if (is_array($matched)) {
            foreach ($matched as $row) {
                if (! is_array($row)) {
                    continue;
                }
                // Каноническое имя предпочтительнее raw_title для exact re-match.
                $name = trim((string) ($row['dish_name'] ?? $row['raw_title'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $dates = $this->normalizeEntryDates($row['dates'] ?? null);
                $entries[] = new PhotoTextScheduleEntryDto(name: $name, dates: $dates);
            }
        }

        $issues = $matchResult['issues'] ?? [];
        if (is_array($issues)) {
            foreach ($issues as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $name = trim((string) ($row['raw_title'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $dates = $this->normalizeEntryDates($row['dates'] ?? null);
                $entries[] = new PhotoTextScheduleEntryDto(name: $name, dates: $dates);
            }
        }

        return $entries;
    }

    /**
     * @return list<string>
     */
    private function normalizeEntryDates(mixed $dates): array
    {
        if (! is_array($dates)) {
            return [];
        }

        $normalized = [];
        foreach ($dates as $date) {
            if (! is_string($date)) {
                continue;
            }
            $trimmed = trim($date);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * {@inheritDoc}
     */
    public function failQueuedOcr(string $runId, ?string $reason = null): void
    {
        $run = $this->runs->findById($runId);
        if ($run === null) {
            return;
        }

        if ($run->status !== SchedulePhotoRunStatus::OcrRunning
            && $run->status !== SchedulePhotoRunStatus::Pending) {
            return;
        }

        $message = trim((string) $reason);
        if ($message === '') {
            $message = 'OCR job failed or timed out.';
        }

        $this->runs->update($runId, [
            'status' => SchedulePhotoRunStatus::Failed,
            'error_message' => mb_substr($message, 0, 2000),
        ]);
    }

    /**
     * Handshake sidecar POST /schedule-ocr.
     */
    private function runOcrHandshake(string $runId): void
    {
        $run = $this->get($runId);
        if ($run->photoPath === null || $run->photoPath === '') {
            throw new FoodDomainException('У прогона нет сохранённого фото.', 422);
        }

        $scope = $this->catalogScope->build($run->baseUrl, $run->restaurantId, $run->categoryIds);
        $photoKey = $this->photoStore->relativeKey($run->photoPath);

        $this->ocrOrchestrator->start(new SchedulePhotoOcrStartDto(
            runId: $run->id,
            photoKey: $photoKey,
            dateFrom: $run->dateFrom,
            dateTo: $run->dateTo,
            restaurantId: $run->restaurantId,
            categoryIds: $run->categoryIds,
            catalogScope: $scope,
            aliasesMarkdown: $this->aliasesReader->readMarkdown(),
            photoMimeType: $this->guessMimeType($photoKey),
        ));
    }

    private function dateToFrom(string $dateFrom): string
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
        if ($from === false) {
            throw new FoodDomainException('Некорректная дата начала графика.', 422);
        }

        return $from->modify('+6 days')->format('Y-m-d');
    }

    private function newUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private function guessMimeType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }

    private function readinessFailureMessage(SchedulePhotoReadinessResultDto $ready): string
    {
        $parts = [];
        if (! $ready->aiAccessOk) {
            $parts[] = $ready->aiAccessMessage;
        }
        if (! $ready->photoTextOk) {
            $parts[] = $ready->photoTextMessage;
        }
        if (! $ready->sidecarOk) {
            $parts[] = $ready->sidecarMessage;
        }

        return $parts !== []
            ? implode(' ', $parts)
            : 'SchedulePhoto: окружение не готово к OCR.';
    }
}
