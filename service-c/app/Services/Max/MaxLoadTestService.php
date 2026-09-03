<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Max\MaxLoadTestDataRepositoryInterface;
use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ApplicationEnvironmentInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Shared\LocalFileWriterInterface;
use App\DTO\Max\LoadTestCleanupResultDto;
use App\DTO\Max\LoadTestPrepareMenuResultDto;
use App\DTO\Max\LoadTestTokenDto;
use App\DTO\Max\LoadTestTokensResultDto;
use App\Support\Max\MaxLoadTestUserIds;
use DateInterval;
use InvalidArgumentException;
use RuntimeException;

/**
 * Выдача Sanctum-токенов и очистка данных для VU нагрузочного стенда.
 */
class MaxLoadTestService implements MaxLoadTestServiceInterface
{
    private const TOKEN_NAME = 'max-miniapp';

    private const TOKEN_ABILITY = 'max-miniapp';

    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly MaxLoadTestDataRepositoryInterface $loadTestDataRepository,
        private readonly MaxMiniAppTokenIssuerInterface $tokenIssuer,
        private readonly ClockInterface $clock,
        private readonly ApplicationEnvironmentInterface $environment,
        private readonly LocalFileWriterInterface $localFileWriter,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function issueTokens(int $count, string $outputPath): LoadTestTokensResultDto
    {
        $this->ensureAllowedEnvironment();

        if ($count < 1) {
            throw new InvalidArgumentException('count должен быть >= 1.');
        }

        if ($outputPath === '') {
            throw new InvalidArgumentException('outputPath не должен быть пустым.');
        }

        $expiresInSeconds = (int) $this->config->get('max.miniapp.token_ttl_seconds', 86_400);
        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
        $expiresAt = $this->clock->now()->add(new DateInterval('PT'.$expiresInSeconds.'S'));

        $tokens = [];

        foreach (MaxLoadTestUserIds::range($count) as $index => $maxUserId) {
            $this->maxUserRepository->upsertLoadTestUser(
                maxUserId: $maxUserId,
                firstName: 'LoadTest'.($index + 1),
                username: 'load_test_'.($index + 1),
                defaultCustomerCategoryId: $defaultCategoryId,
            );

            $this->tokenIssuer->revokeNamedTokens($maxUserId, self::TOKEN_NAME);

            $plainTextToken = $this->tokenIssuer->createToken(
                $maxUserId,
                self::TOKEN_NAME,
                [self::TOKEN_ABILITY],
                $expiresAt,
            );

            $tokens[] = new LoadTestTokenDto(
                maxUserId: $maxUserId,
                token: $plainTextToken,
            );
        }

        $this->writeTokensFile($outputPath, $tokens);

        return new LoadTestTokensResultDto(
            tokens: $tokens,
            outputPath: $outputPath,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function prepareMenu(): LoadTestPrepareMenuResultDto
    {
        $this->ensureAllowedEnvironment();

        $restaurantIds = $this->loadTestDataRepository->listActiveRestaurantIds();

        if ($restaurantIds === []) {
            return new LoadTestPrepareMenuResultDto(
                dishesEnabled: 0,
                restaurantIds: [],
            );
        }

        $dishesEnabled = $this->loadTestDataRepository->enableUnavailableDishesForRestaurants($restaurantIds);

        $this->catalogCacheInvalidator->invalidateAll();

        return new LoadTestPrepareMenuResultDto(
            dishesEnabled: $dishesEnabled,
            restaurantIds: $restaurantIds,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup(int $count): LoadTestCleanupResultDto
    {
        $this->ensureAllowedEnvironment();

        if ($count < 1) {
            throw new InvalidArgumentException('count должен быть >= 1.');
        }

        $maxUserIds = MaxLoadTestUserIds::range($count);

        // Сначала заказы: max_food_orders.cart_id → max_carts без cascade.
        $ordersDeleted = $this->loadTestDataRepository->deleteOrdersForMaxUserIds($maxUserIds);
        $cartsDeleted = $this->loadTestDataRepository->deleteCartsForMaxUserIds($maxUserIds);

        return new LoadTestCleanupResultDto(
            ordersDeleted: $ordersDeleted,
            cartsDeleted: $cartsDeleted,
            maxUserIds: $maxUserIds,
        );
    }

    /**
     * Разрешает выполнение только в local/testing.
     */
    private function ensureAllowedEnvironment(): void
    {
        if (! $this->environment->is(['local', 'testing'])) {
            throw new RuntimeException(
                'Команды max:load-test:* доступны только при APP_ENV=local или testing.',
            );
        }
    }

    /**
     * Пишет JSON-массив токенов для k6 (TOKENS_FILE).
     *
     * @param  list<LoadTestTokenDto>  $tokens
     */
    private function writeTokensFile(string $outputPath, array $tokens): void
    {
        $this->localFileWriter->ensureDirectory(dirname($outputPath));

        $payload = array_map(
            static fn (LoadTestTokenDto $token): array => $token->toArray(),
            $tokens,
        );

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Не удалось сериализовать токены в JSON.');
        }

        $this->localFileWriter->put($outputPath, $json.PHP_EOL);
    }
}
