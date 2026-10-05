<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\SourceMenuLineDto;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\Menu\DailyMenuLineType;
use App\Exceptions\Food\FoodDomainException;
use App\Infrastructure\Briskly\HttpBrisklySyncVpsCatalogGateway;
use PHPUnit\Framework\TestCase;

/**
 * HTTP-gateway remote VPS catalog: restaurants / categories / source_lines + 403/503.
 */
final class HttpBrisklySyncVpsCatalogGatewayTest extends TestCase
{
    public function test_list_restaurants_sends_token_and_maps_items(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 11, 'name' => 'Столовая'],
                        ['id' => 0, 'name' => 'skip'],
                        'bad-row',
                    ],
                ],
            ],
        ]);

        $gateway = $this->gateway($http);
        $items = $gateway->listRestaurants();

        $this->assertCount(1, $items);
        $this->assertSame(11, $items[0]->id);
        $this->assertSame('Столовая', $items[0]->name);
        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/restaurants', $http->lastUrl);
        $this->assertSame('https://prod.example', $http->lastBaseUrl);
        $this->assertSame(30, $http->lastTimeout);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertSame('application/json', $http->lastHeaders['Accept'] ?? null);
    }

    public function test_list_vps_categories_reads_catalog_categories(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 7, 'name' => 'Активный'],
                    ],
                ],
            ],
            '/api/food/phototext/catalog?restaurant_id=7' => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'categories' => [
                            ['id' => 21, 'name' => 'Супы'],
                            ['id' => 22, 'name' => 'Салаты'],
                        ],
                    ],
                ],
            ],
        ]);

        $categories = $this->gateway($http)->listVpsCategories(7);

        $this->assertCount(2, $categories);
        $this->assertSame(21, $categories[0]->id);
        $this->assertSame('Супы', $categories[0]->name);
        $this->assertSame('/api/food/phototext/catalog?restaurant_id=7', $http->lastUrl);
    }

    public function test_collect_source_lines_maps_dto_and_query(): void
    {
        $expectedPath = '/api/food/phototext/briskly-source-lines?'.http_build_query([
            'restaurant_id' => 7,
            'vps_category_id' => 21,
            'search_text' => 'борщ',
        ]);

        $http = $this->recordingHttpClient([
            $expectedPath => [
                'status' => 200,
                'body' => [
                    'source_lines' => [
                        [
                            'line_key' => 'single:101',
                            'type' => 'single',
                            'display_name' => 'Борщ',
                            'price' => '150.00',
                            'part_dish_ids' => [101],
                            'briskly_create_name' => 'Борщ, 300 г',
                        ],
                        [
                            'line_key' => '',
                            'type' => 'single',
                        ],
                    ],
                ],
            ],
        ]);

        $lines = $this->gateway($http)->collectSourceLines(7, 21, 'борщ');

        $this->assertCount(1, $lines);
        $this->assertInstanceOf(SourceMenuLineDto::class, $lines[0]);
        $this->assertSame('single:101', $lines[0]->lineKey);
        $this->assertSame(DailyMenuLineType::Single, $lines[0]->type);
        $this->assertSame('Борщ', $lines[0]->displayName);
        $this->assertSame('150.00', $lines[0]->price);
        $this->assertSame([101], $lines[0]->partDishIds);
        $this->assertSame('Борщ, 300 г', $lines[0]->brisklyCreateName);
        $this->assertSame($expectedPath, $http->lastUrl);
    }

    public function test_assert_category_belongs_throws_when_missing(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 7, 'name' => 'Активный'],
                    ],
                ],
            ],
            '/api/food/phototext/catalog?restaurant_id=7' => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'categories' => [
                            ['id' => 21, 'name' => 'Супы'],
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $this->gateway($http)->assertCategoryBelongs(7, 999);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertStringContainsString('Категория меню не найдена', $exception->getMessage());
        }
    }

    public function test_remote_403_maps_to_ai_access_message(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 403,
                'body' => ['message' => 'Доступ AI к базе не разрешён.'],
                'successful' => false,
            ],
        ]);

        try {
            $this->gateway($http)->listRestaurants();
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(403, $exception->statusCode());
            $this->assertStringContainsString('активный доступ AI', $exception->getMessage());
        }
    }

    public function test_remote_500_maps_to_503_unavailable(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 500,
                'body' => ['message' => 'boom'],
                'successful' => false,
            ],
        ]);

        try {
            $this->gateway($http)->listRestaurants();
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('Remote Briskly source недоступен', $exception->getMessage());
            $this->assertStringContainsString('/api/food/phototext/restaurants', $exception->getMessage());
        }
    }

    public function test_empty_agent_token_returns_503_without_http(): void
    {
        $http = $this->recordingHttpClient([]);
        $gateway = new HttpBrisklySyncVpsCatalogGateway(
            $http,
            'https://prod.example',
            '',
            30,
        );

        try {
            $gateway->listRestaurants();
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('PHOTOTEXT_AGENT_TOKEN', $exception->getMessage());
            $this->assertSame(0, $http->callCount);
        }
    }

    public function test_empty_base_url_returns_503_without_http(): void
    {
        $http = $this->recordingHttpClient([]);
        $gateway = new HttpBrisklySyncVpsCatalogGateway(
            $http,
            '',
            'agent-token-test',
            30,
        );

        try {
            $gateway->listRestaurants();
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('source_base_url пуст', $exception->getMessage());
            $this->assertSame(0, $http->callCount);
        }
    }

    /**
     * @param  object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
     *     lastBaseUrl: ?string,
     *     lastTimeout: int,
     *     callCount: int
     * }&HttpClientInterface  $http
     */
    private function gateway(HttpClientInterface $http): HttpBrisklySyncVpsCatalogGateway
    {
        return new HttpBrisklySyncVpsCatalogGateway(
            $http,
            'https://prod.example',
            'agent-token-test',
            30,
        );
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
     *     lastBaseUrl: ?string,
     *     lastTimeout: int,
     *     callCount: int
     * }&HttpClientInterface
     */
    private function recordingHttpClient(array $routes): HttpClientInterface
    {
        return new class($routes) implements HttpClientInterface
        {
            public string $lastMethod = '';

            public string $lastUrl = '';

            /** @var array<string, string> */
            public array $lastHeaders = [];

            public ?string $lastBaseUrl = null;

            public int $lastTimeout = 0;

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
                $this->lastTimeout = $timeoutSeconds;

                $route = $this->routes[$url] ?? null;
                if ($route === null) {
                    return new HttpResponseDto(status: 404, body: '{"message":"not stubbed"}', successful: false);
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
    }
}
