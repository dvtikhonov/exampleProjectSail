<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Admin proxy restaurants/categories при source_remote (через Http VPS gateway).
 */
class AdminBrisklySyncRemoteCatalogApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string RESTAURANTS = '/api/food/admin/briskly-sync/restaurants';

    private const string VPS_CATEGORIES = '/api/food/admin/briskly-sync/vps-categories';

    /** Подготовка: remote source + fake HttpClient. */
    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'briskly_sync.source_remote' => true,
            'briskly_sync.source_base_url' => 'https://prod.example',
            'briskly_sync.source_timeout_seconds' => 30,
            'phototext.agent_token' => 'remote-admin-agent-token',
        ]);
    }

    /** max_manager получает рестораны с remote PhotoText (не из локальной БД). */
    public function test_max_manager_lists_restaurants_via_remote_gateway(): void
    {
        $this->bindRemoteHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 501, 'name' => 'Remote Столовая'],
                    ],
                ],
            ],
        ]);

        $manager = $this->maxManagerAuth(32_001);

        $this->getJson(self::RESTAURANTS, $manager['headers'])
            ->assertOk()
            ->assertJsonPath('restaurants.0.id', 501)
            ->assertJsonPath('restaurants.0.name', 'Remote Столовая');
    }

    /** max_manager получает категории с remote catalog. */
    public function test_max_manager_lists_vps_categories_via_remote_gateway(): void
    {
        $this->bindRemoteHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 501, 'name' => 'Remote Столовая'],
                    ],
                ],
            ],
            '/api/food/phototext/catalog?restaurant_id=501' => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'categories' => [
                            ['id' => 77, 'name' => 'Remote Супы'],
                        ],
                    ],
                ],
            ],
        ]);

        $manager = $this->maxManagerAuth(32_002);

        $this->getJson(self::VPS_CATEGORIES.'?restaurant_id=501', $manager['headers'])
            ->assertOk()
            ->assertJsonPath('categories.0.id', 77)
            ->assertJsonPath('categories.0.name', 'Remote Супы');
    }

    /** Remote 403 (нет AI на prod) пробрасывается admin API. */
    public function test_remote_ai_forbidden_returns_403(): void
    {
        $this->bindRemoteHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => 'Доступ AI к базе не разрешён.'],
                'successful' => false,
            ],
        ]);

        $manager = $this->maxManagerAuth(32_003);

        $this->getJson(self::RESTAURANTS, $manager['headers'])
            ->assertForbidden()
            ->assertJsonPath(
                'message',
                'Remote Briskly source запрещён (403): нужен активный доступ AI на prod.',
            );
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     */
    private function bindRemoteHttp(array $routes): void
    {
        $http = new class($routes) implements HttpClientInterface
        {
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
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    private function maxManagerAuth(int $maxUserId): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'RemoteMaxManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
