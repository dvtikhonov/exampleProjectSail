<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Food\MenuCategory;
use App\Models\Food\Restaurant;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: GET restaurants/catalog проксируют выбранный BASE_URL и отвергают URL вне allow-list.
 */
class AdminSchedulePhotoCatalogProxyApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string RESTAURANTS = '/api/food/admin/schedule-photo/restaurants';

    private const string CATALOG = '/api/food/admin/schedule-photo/catalog';

    private const string REMOTE_ORIGIN = 'https://max-dev.94-228-117-27.sslip.io';

    private const string LOCAL_ORIGIN = 'http://host.docker.internal:8083';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'phototext.agent_token' => 'schedule-photo-agent-token',
            'phototext.write_token' => 'schedule-photo-write-token',
            'schedule_photo.phototext_probe_timeout_seconds' => 10,
            'schedule_photo.allowed_base_urls' => [
                'http://localhost:8083',
                self::LOCAL_ORIGIN,
                self::REMOTE_ORIGIN,
            ],
        ]);
    }

    public function test_restaurants_requires_authentication(): void
    {
        $this->getJson($this->restaurantsUrl(self::REMOTE_ORIGIN))
            ->assertUnauthorized();
    }

    public function test_catalog_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 62_001,
            'first_name' => 'NoRole',
        ]));

        $this->getJson($this->catalogUrl(self::REMOTE_ORIGIN, 501), $auth['headers'])
            ->assertForbidden();
    }

    public function test_restaurants_proxy_remote_origin_and_do_not_touch_local_menu(): void
    {
        $http = $this->bindProxyHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 501, 'name' => 'Remote Столовая'],
                    ],
                ],
            ],
        ]);

        $menu = $this->menuManagerAuth(62_010);

        $this->getJson($this->restaurantsUrl(self::REMOTE_ORIGIN.'/'), $menu['headers'])
            ->assertOk()
            ->assertJsonPath('restaurants.0.id', 501)
            ->assertJsonPath('restaurants.0.name', 'Remote Столовая')
            ->assertJsonCount(1, 'restaurants');

        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/restaurants', $http->lastUrl);
        $this->assertSame(self::REMOTE_ORIGIN, $http->lastBaseUrl);
        $this->assertSame('schedule-photo-agent-token', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);
        $this->assertSame(0, Restaurant::query()->count());
        $this->assertSame(0, MenuCategory::query()->count());
    }

    public function test_restaurants_proxy_local_docker_origin_through_http(): void
    {
        $http = $this->bindProxyHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 7, 'name' => 'Локальная столовая'],
                    ],
                ],
            ],
        ]);

        $menu = $this->menuManagerAuth(62_011);

        $this->getJson($this->restaurantsUrl(self::LOCAL_ORIGIN), $menu['headers'])
            ->assertOk()
            ->assertJsonPath('restaurants.0.id', 7)
            ->assertJsonPath('restaurants.0.name', 'Локальная столовая');

        $this->assertSame(self::LOCAL_ORIGIN, $http->lastBaseUrl);
        $this->assertSame(1, $http->callCount);
    }

    public function test_catalog_proxies_selected_origin_without_local_restaurant(): void
    {
        $http = $this->bindProxyHttp([
            '/api/food/phototext/catalog?restaurant_id=501' => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'restaurant_id' => 501,
                        'restaurant_name' => 'Remote Столовая',
                        'categories' => [
                            [
                                'id' => 77,
                                'name' => 'Remote Супы',
                                'dishes' => [
                                    ['id' => 900, 'name' => 'Борщ'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $menu = $this->menuManagerAuth(62_012);

        $this->getJson($this->catalogUrl(self::REMOTE_ORIGIN, 501), $menu['headers'])
            ->assertOk()
            ->assertJsonPath('restaurant_id', 501)
            ->assertJsonPath('restaurant_name', 'Remote Столовая')
            ->assertJsonPath('categories.0.id', 77)
            ->assertJsonPath('categories.0.name', 'Remote Супы')
            ->assertJsonMissingPath('categories.0.dishes');

        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/catalog?restaurant_id=501', $http->lastUrl);
        $this->assertSame(self::REMOTE_ORIGIN, $http->lastBaseUrl);
        $this->assertSame('schedule-photo-agent-token', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);
        $this->assertSame(0, Restaurant::query()->count());
        $this->assertSame(0, MenuCategory::query()->count());
    }

    public function test_rejects_base_url_outside_allow_list(): void
    {
        $http = $this->bindProxyHttp([]);
        $menu = $this->menuManagerAuth(62_020);

        $this->getJson($this->restaurantsUrl('http://169.254.169.254'), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);

        $this->getJson($this->catalogUrl('http://169.254.169.254', 1), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);

        $this->assertSame(0, $http->callCount);
    }

    public function test_restaurants_require_base_url(): void
    {
        $menu = $this->menuManagerAuth(62_021);

        $this->getJson(self::RESTAURANTS, $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);
    }

    public function test_catalog_rejects_missing_and_invalid_restaurant_id(): void
    {
        $http = $this->bindProxyHttp([]);
        $menu = $this->menuManagerAuth(62_022);

        $this->getJson(self::CATALOG.'?'.http_build_query([
            'base_url' => self::REMOTE_ORIGIN,
        ]), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_id']);

        $this->getJson($this->catalogUrl(self::REMOTE_ORIGIN, 0), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_id']);

        $this->getJson(self::CATALOG.'?'.http_build_query([
            'base_url' => self::REMOTE_ORIGIN,
            'restaurant_id' => 'abc',
        ]), $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['restaurant_id']);

        $this->assertSame(0, $http->callCount);
    }

    public function test_remote_unauthorized_is_passed_through(): void
    {
        $this->bindProxyHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 401,
                'body' => ['message' => 'unauthorized'],
                'successful' => false,
            ],
        ]);

        $menu = $this->menuManagerAuth(62_030);

        $this->getJson($this->restaurantsUrl(self::REMOTE_ORIGIN), $menu['headers'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'PhotoText: неверный токен (401).');
    }

    private function restaurantsUrl(string $baseUrl): string
    {
        return self::RESTAURANTS.'?'.http_build_query(['base_url' => $baseUrl]);
    }

    private function catalogUrl(string $baseUrl, int $restaurantId): string
    {
        return self::CATALOG.'?'.http_build_query([
            'base_url' => $baseUrl,
            'restaurant_id' => $restaurantId,
        ]);
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
     *     lastBaseUrl: ?string,
     *     callCount: int
     * }&HttpClientInterface
     */
    private function bindProxyHttp(array $routes): HttpClientInterface
    {
        $http = new class($routes) implements HttpClientInterface
        {
            public string $lastMethod = '';

            public string $lastUrl = '';

            /** @var array<string, string> */
            public array $lastHeaders = [];

            public ?string $lastBaseUrl = null;

            public int $callCount = 0;

            /**
             * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
             */
            public function __construct(private readonly array $routes) {}

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->callCount++;
                $this->lastMethod = $method;
                $this->lastUrl = $url;
                $this->lastHeaders = $headers;
                $this->lastBaseUrl = $baseUrl;

                $route = $this->routes[$url] ?? null;
                if ($route === null) {
                    return new HttpResponseDto(
                        status: 404,
                        body: json_encode(['message' => 'not stubbed: '.$url], JSON_THROW_ON_ERROR),
                        successful: false,
                    );
                }

                $status = $route['status'];
                $successful = $route['successful'] ?? ($status >= 200 && $status < 300);
                $body = $route['body'];
                $encoded = is_string($body)
                    ? $body
                    : json_encode($body, JSON_THROW_ON_ERROR);

                return new HttpResponseDto(status: $status, body: $encoded, successful: $successful);
            }
        };

        $this->app->instance(HttpClientInterface::class, $http);

        return $http;
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function menuManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'MenuManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MenuManager,
        );
    }
}
