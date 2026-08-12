<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\MenuQueryServiceInterface;
use App\DTO\Food\Menu\DishDto;
use App\DTO\Food\Menu\MenuCategoryDto;
use App\DTO\Food\Menu\MenuDto;
use App\DTO\Food\Shared\RestaurantSummaryDto;
use App\Services\Food\Menu\CachingMenuQueryService;
use App\Services\Food\Menu\MenuCatalogCacheInvalidator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Hit/miss и bump версии кэша каталога еды.
 */
class CachingMenuQueryServiceTest extends TestCase
{
    /** @var object{list: int, menu: int} */
    private object $innerCalls;

    /** Подготовка окружения перед тестом. */
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->innerCalls = (object) ['list' => 0, 'menu' => 0];
    }

    /** Первый list — miss (inner), второй — hit из кэша. */
    public function test_list_active_restaurants_hit_after_miss(): void
    {
        $service = $this->makeCachingService();

        $first = $service->listActiveRestaurants();
        $second = $service->listActiveRestaurants();

        $this->assertSame(1, $this->innerCalls->list);
        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame($first[0]->id, $second[0]->id);
        $this->assertSame($first[0]->name, $second[0]->name);
        $this->assertTrue(Cache::has($service->restaurantsCacheKey()));
    }

    /** Первый menu — miss (inner), второй — hit из кэша. */
    public function test_get_restaurant_menu_hit_after_miss(): void
    {
        $service = $this->makeCachingService();

        $first = $service->getRestaurantMenu(10, false);
        $second = $service->getRestaurantMenu(10, false);

        $this->assertSame(1, $this->innerCalls->menu);
        $this->assertSame($first->toArray(), $second->toArray());
        $this->assertTrue(Cache::has($service->menuCacheKey(10, false)));
    }

    /** Ключи pub и all для меню не смешиваются. */
    public function test_menu_cache_keys_differ_for_include_unavailable(): void
    {
        $service = $this->makeCachingService();

        $service->getRestaurantMenu(10, false);
        $service->getRestaurantMenu(10, true);

        $this->assertSame(2, $this->innerCalls->menu);
        $this->assertTrue(Cache::has($service->menuCacheKey(10, false)));
        $this->assertTrue(Cache::has($service->menuCacheKey(10, true)));
        $this->assertNotSame(
            $service->menuCacheKey(10, false),
            $service->menuCacheKey(10, true),
        );
    }

    /** Bump версии сбрасывает hit: следующий запрос снова идёт во inner. */
    public function test_version_bump_invalidates_cached_hits(): void
    {
        $service = $this->makeCachingService();
        $invalidator = new MenuCatalogCacheInvalidator(Cache::store());

        $service->listActiveRestaurants();
        $service->getRestaurantMenu(10, false);
        $this->assertSame(1, $this->innerCalls->list);
        $this->assertSame(1, $this->innerCalls->menu);

        $versionBefore = $service->catalogVersion();
        $invalidator->invalidateAll();

        $this->assertSame($versionBefore + 1, $service->catalogVersion());

        $service->listActiveRestaurants();
        $service->getRestaurantMenu(10, false);

        $this->assertSame(2, $this->innerCalls->list);
        $this->assertSame(2, $this->innerCalls->menu);
    }

    /** При catalog_cache_enabled=false кэш не используется. */
    public function test_disabled_cache_always_calls_inner(): void
    {
        $service = $this->makeCachingService(enabled: false);

        $service->listActiveRestaurants();
        $service->listActiveRestaurants();
        $service->getRestaurantMenu(10, false);
        $service->getRestaurantMenu(10, false);

        $this->assertSame(2, $this->innerCalls->list);
        $this->assertSame(2, $this->innerCalls->menu);
        $this->assertFalse(Cache::has($service->restaurantsCacheKey()));
    }

    /**
     * Собирает декоратор с фейковым inner-сервисом.
     */
    private function makeCachingService(bool $enabled = true): CachingMenuQueryService
    {
        $calls = $this->innerCalls;

        $inner = new class($calls) implements MenuQueryServiceInterface
        {
            /** @param  object{list: int, menu: int}  $calls */
            public function __construct(private object $calls) {}

            public function listActiveRestaurants(): array
            {
                $this->calls->list++;

                return [
                    new RestaurantSummaryDto(id: 1, name: 'Bistro', address: 'Main St'),
                ];
            }

            public function getRestaurantMenu(int $restaurantId, bool $includeUnavailable = false): MenuDto
            {
                $this->calls->menu++;

                return new MenuDto(
                    restaurantId: $restaurantId,
                    restaurantName: 'Bistro',
                    categories: [
                        new MenuCategoryDto(
                            id: 5,
                            name: 'Main',
                            isComboAvailable: false,
                            dishes: [
                                new DishDto(
                                    id: 100,
                                    name: $includeUnavailable ? 'All Pasta' : 'Pub Pasta',
                                    price: '250.00',
                                    isAvailable: true,
                                    imageUrl: 'https://example.test/dish.jpg',
                                ),
                            ],
                        ),
                    ],
                );
            }
        };

        return new CachingMenuQueryService(
            inner: $inner,
            cache: Cache::store(),
            ttlSeconds: 600,
            enabled: $enabled,
        );
    }
}
