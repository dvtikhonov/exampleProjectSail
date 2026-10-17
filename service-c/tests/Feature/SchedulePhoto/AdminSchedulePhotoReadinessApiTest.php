<?php

declare(strict_types=1);

namespace Tests\Feature\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\AuthenticatesMaxMiniAppUser;
use Tests\Support\ResetsFoodDomainTables;
use Tests\TestCase;

/**
 * Feature: POST /api/food/admin/schedule-photo/readiness.
 */
class AdminSchedulePhotoReadinessApiTest extends TestCase
{
    use AuthenticatesMaxMiniAppUser;
    use RefreshDatabase;
    use ResetsFoodDomainTables;

    private const string ENDPOINT = '/api/food/admin/schedule-photo/readiness';

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetFoodDomainTables();

        config([
            'phototext.agent_token' => 'schedule-photo-agent-token',
            'schedule_photo.orchestrator_base_url' => 'http://sidecar.test:8791',
            'schedule_photo.sidecar_health_timeout_seconds' => 5,
            'schedule_photo.phototext_probe_timeout_seconds' => 10,
            'schedule_photo.allowed_base_urls' => [
                'http://localhost:8083',
                'http://host.docker.internal:8083',
                'http://vps.example',
            ],
        ]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson(self::ENDPOINT, ['base_url' => 'http://localhost:8083'])
            ->assertUnauthorized();
    }

    public function test_forbidden_without_menu_manager_role(): void
    {
        $auth = $this->authenticateMaxUser(MaxUser::query()->create([
            'max_user_id' => 61_001,
            'first_name' => 'NoRole',
        ]));

        $this->postJson(self::ENDPOINT, ['base_url' => 'http://localhost:8083'], $auth['headers'])
            ->assertForbidden();
    }

    public function test_validation_requires_base_url(): void
    {
        $menu = $this->menuManagerAuth(61_002);

        $this->postJson(self::ENDPOINT, [], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);
    }

    public function test_rejects_base_url_outside_allow_list_ssrf(): void
    {
        $menu = $this->menuManagerAuth(61_020);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://169.254.169.254',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['base_url']);
    }

    public function test_config_returns_ui_allow_list_without_localhost(): void
    {
        config([
            'schedule_photo.local_base_url' => 'http://host.docker.internal:8083',
            'schedule_photo.default_base_url' => 'http://host.docker.internal:8083',
            'schedule_photo.allowed_base_urls' => [
                'http://host.docker.internal:8083',
                'http://localhost:8083',
                'https://max-dev.94-228-117-27.sslip.io',
            ],
        ]);

        $menu = $this->menuManagerAuth(61_021);

        $response = $this->getJson('/api/food/admin/schedule-photo/config', $menu['headers'])
            ->assertOk()
            ->assertJsonPath('default_base_url', 'http://host.docker.internal:8083');

        $values = array_column($response->json('allowed_base_urls') ?? [], 'value');
        $this->assertSame(
            [
                'http://host.docker.internal:8083',
                'https://max-dev.94-228-117-27.sslip.io',
            ],
            $values,
        );
    }

    public function test_ok_when_ai_phototext_and_sidecar_ready(): void
    {
        $this->enableAiAccessForMaxManager(61_010);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_003);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://localhost:8083/',
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_access.ok', true)
            ->assertJsonPath('phototext.ok', true)
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_unprocessable_when_ai_access_disabled(): void
    {
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_004);

        $response = $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://localhost:8083',
        ], $menu['headers']);

        $response->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false);

        $this->assertStringContainsString(
            'Вкл. доступ AI',
            (string) $response->json('ai_access.message'),
        );
    }

    public function test_unprocessable_when_local_phototext_token_rejected(): void
    {
        $this->enableAiAccessForMaxManager(61_011);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 401,
                'body' => ['message' => 'unauthorized'],
                'successful' => false,
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_005);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://localhost:8083',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', true)
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'Локальный PhotoText: неверный токен (401).')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_origin_probe_200_confirms_ai_without_local_flag(): void
    {
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_030);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ai_access.ok', true)
            ->assertJsonPath('ai_access.message', 'AI-доступ активен.')
            ->assertJsonPath('phototext.ok', true)
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_origin_probe_403_denies_only_ai_access(): void
    {
        $this->enableAiAccessForMaxManager(61_031);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => 'Доступ AI к базе не разрешён.'],
                'successful' => false,
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_032);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false)
            ->assertJsonPath('ai_access.message', 'Доступ AI к базе не разрешён.')
            ->assertJsonPath('phototext.ok', true)
            ->assertJsonPath('phototext.message', 'VPS PhotoText доступен.')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_origin_probe_403_unknown_body_does_not_confirm_ai_or_phototext(): void
    {
        $foreign = 'WAF blocked /internal stack trace APP_DEBUG';
        $this->enableAiAccessForMaxManager(61_035);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => $foreign],
                'successful' => false,
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_036);

        $response = $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false)
            ->assertJsonPath('ai_access.message', 'Флаг AI на выбранном сервере не проверен.')
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'VPS PhotoText: HTTP 403.')
            ->assertJsonPath('sidecar.ok', true);

        $this->assertStringNotContainsString($foreign, (string) $response->getContent());
    }

    public function test_remote_probe_failure_does_not_confirm_ai_flag(): void
    {
        $this->enableAiAccessForMaxManager(61_033);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 401,
                'body' => ['message' => 'unauthorized'],
                'successful' => false,
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_034);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false)
            ->assertJsonPath('ai_access.message', 'Флаг AI на выбранном сервере не проверен.')
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'VPS PhotoText: неверный токен (401).')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_redirect_does_not_confirm_ai_flag(): void
    {
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 302,
                'body' => '',
                'successful' => false,
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_040);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false)
            ->assertJsonPath('ai_access.message', 'Флаг AI на выбранном сервере не проверен.')
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'VPS PhotoText: HTTP 302.')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_non_json_200_does_not_confirm_ai_flag(): void
    {
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => '<html>login</html>',
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_041);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', false)
            ->assertJsonPath('ai_access.message', 'Флаг AI на выбранном сервере не проверен.')
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'VPS PhotoText: пустой или неверный JSON.')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_remote_empty_restaurants_json_200_confirms_ai_only(): void
    {
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => ['restaurants' => []],
            ],
            '/health' => [
                'status' => 200,
                'body' => ['ok' => true],
            ],
        ]);

        $menu = $this->menuManagerAuth(61_042);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://vps.example',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('ai_access.ok', true)
            ->assertJsonPath('ai_access.message', 'AI-доступ активен.')
            ->assertJsonPath('phototext.ok', false)
            ->assertJsonPath('phototext.message', 'VPS PhotoText: пустой список ресторанов.')
            ->assertJsonPath('sidecar.ok', true);
    }

    public function test_unprocessable_when_sidecar_down(): void
    {
        $this->enableAiAccessForMaxManager(61_012);
        $this->bindProbeHttp([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 1, 'name' => 'Столовая'],
                    ],
                ],
            ],
            '/health' => [
                'status' => 503,
                'body' => ['message' => 'down'],
                'successful' => false,
            ],
        ]);

        $menu = $this->menuManagerAuth(61_006);

        $this->postJson(self::ENDPOINT, [
            'base_url' => 'http://localhost:8083',
        ], $menu['headers'])
            ->assertUnprocessable()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('sidecar.ok', false)
            ->assertJsonPath('sidecar.message', 'Sidecar LLM: HTTP 503.');
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     */
    private function bindProbeHttp(array $routes): void
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
                bool $followRedirects = true,
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

    private function enableAiAccessForMaxManager(int $maxUserId): void
    {
        $auth = $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => 'AiMaxManager'.$maxUserId,
            ])),
            FoodOrderAdminRole::MaxManager,
        );

        $auth['user']->forceFill([
            'ai_access_until' => Carbon::now()->addMinutes(30),
        ])->save();
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
