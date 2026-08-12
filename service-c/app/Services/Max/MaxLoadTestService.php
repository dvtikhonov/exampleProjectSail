<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Menu\MenuCatalogCacheInvalidatorInterface;
use App\Contracts\Max\MaxLoadTestServiceInterface;
use App\DTO\Max\LoadTestCleanupResultDto;
use App\DTO\Max\LoadTestPrepareMenuResultDto;
use App\DTO\Max\LoadTestTokenDto;
use App\DTO\Max\LoadTestTokensResultDto;
use App\Models\Food\Cart;
use App\Models\Food\Dish;
use App\Models\Food\FoodOrder;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use App\Support\Max\MaxLoadTestUserIds;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\Filesystem;
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
        private readonly Repository $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly MenuCatalogCacheInvalidatorInterface $catalogCacheInvalidator,
        private readonly Filesystem $files,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function issueTokens(int $count, ?string $outputPath = null): LoadTestTokensResultDto
    {
        $this->ensureAllowedEnvironment();

        if ($count < 1) {
            throw new InvalidArgumentException('count должен быть >= 1.');
        }

        $outputPath ??= MaxLoadTestUserIds::defaultTokenFilePath();
        $expiresInSeconds = (int) $this->config->get('max.miniapp.token_ttl_seconds', 86_400);
        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();

        $tokens = [];

        foreach (MaxLoadTestUserIds::range($count) as $index => $maxUserId) {
            $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $maxUserId]);

            if (! $maxUser->exists) {
                $maxUser->fill([
                    'first_name' => 'LoadTest'.($index + 1),
                    'username' => 'load_test_'.($index + 1),
                    'language_code' => 'ru',
                ]);
            }

            if ($maxUser->customer_category_id === null) {
                $maxUser->customer_category_id = $defaultCategoryId;
            }

            $maxUser->save();

            $maxUser->tokens()->where('name', self::TOKEN_NAME)->delete();

            $accessToken = $maxUser->createToken(
                self::TOKEN_NAME,
                [self::TOKEN_ABILITY],
                now()->addSeconds($expiresInSeconds),
            );

            $tokens[] = new LoadTestTokenDto(
                maxUserId: $maxUserId,
                token: $accessToken->plainTextToken,
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

        $restaurantIds = Restaurant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($restaurantIds === []) {
            return new LoadTestPrepareMenuResultDto(
                dishesEnabled: 0,
                restaurantIds: [],
            );
        }

        $dishesEnabled = Dish::query()
            ->where('is_available', false)
            ->whereHas(
                'menuCategory',
                static fn ($query) => $query->whereIn('restaurant_id', $restaurantIds),
            )
            ->update(['is_available' => true]);

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
        $ordersDeleted = FoodOrder::query()->whereIn('max_user_id', $maxUserIds)->delete();
        $cartsDeleted = Cart::query()->whereIn('max_user_id', $maxUserIds)->delete();

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
        if (! app()->environment(['local', 'testing'])) {
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
        $directory = dirname($outputPath);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $payload = array_map(
            static fn (LoadTestTokenDto $token): array => $token->toArray(),
            $tokens,
        );

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new RuntimeException('Не удалось сериализовать токены в JSON.');
        }

        $this->files->put($outputPath, $json.PHP_EOL);
    }
}
