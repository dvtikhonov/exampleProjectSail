<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\SchedulePhoto;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Shared\HttpResponseDto;
use App\Enums\Food\PhotoText\PhotoTextMatchIssueCode;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\Infrastructure\HttpSchedulePhotoPhotoTextClient;
use App\Modules\SchedulePhoto\Support\SchedulePhotoOriginAllowList;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Unit: HTTP-клиент PhotoText по allow-list BASE_URL.
 */
final class HttpSchedulePhotoPhotoTextClientTest extends TestCase
{
    private const string LOCAL_ORIGIN = 'http://host.docker.internal:8083';

    private const string REMOTE_ORIGIN = 'https://max-dev.94-228-117-27.sslip.io';

    public function test_list_restaurants_sends_agent_token_and_maps_json(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => [
                    'restaurants' => [
                        ['id' => 11, 'name' => 'Столовая'],
                        ['id' => 0, 'name' => 'skip'],
                        ['id' => 12, 'name' => '   '],
                        'bad-row',
                    ],
                ],
            ],
        ]);

        $items = $this->client($http)->listRestaurants(self::REMOTE_ORIGIN.'/');

        $this->assertCount(1, $items);
        $this->assertSame(11, $items[0]->id);
        $this->assertSame('Столовая', $items[0]->name);
        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/restaurants', $http->lastUrl);
        $this->assertNull($http->lastJsonBody);
        $this->assertSame(self::REMOTE_ORIGIN, $http->lastBaseUrl);
        $this->assertSame(10, $http->lastTimeout);
        $this->assertSame('application/json', $http->lastHeaders['Accept'] ?? null);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);
    }

    public function test_get_catalog_sends_restaurant_id_and_maps_categories(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/catalog?restaurant_id=7' => [
                'status' => 200,
                'body' => [
                    'catalog' => [
                        'restaurant_id' => 7,
                        'restaurant_name' => 'Столовая',
                        'categories' => [
                            [
                                'id' => 21,
                                'name' => 'Супы',
                                'dishes' => [
                                    ['id' => 101, 'name' => 'Борщ'],
                                    ['id' => 0, 'name' => 'skip'],
                                ],
                            ],
                            'bad-row',
                        ],
                    ],
                ],
            ],
        ]);

        $catalog = $this->client($http)->getCatalog(self::LOCAL_ORIGIN, 7);

        $this->assertSame('GET', $http->lastMethod);
        $this->assertSame('/api/food/phototext/catalog?restaurant_id=7', $http->lastUrl);
        $this->assertSame(self::LOCAL_ORIGIN, $http->lastBaseUrl);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);
        $this->assertSame(7, $catalog->restaurantId);
        $this->assertSame('Столовая', $catalog->restaurantName);
        $this->assertCount(1, $catalog->categories);
        $this->assertSame(21, $catalog->categories[0]->id);
        $this->assertSame('Супы', $catalog->categories[0]->name);
        $this->assertCount(1, $catalog->categories[0]->dishes);
        $this->assertSame(101, $catalog->categories[0]->dishes[0]->id);
        $this->assertSame('Борщ', $catalog->categories[0]->dishes[0]->name);
    }

    public function test_match_sends_agent_token_only_and_maps_result(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/schedule/match' => [
                'status' => 200,
                'body' => $this->schedulePayload(applied: false),
            ],
        ]);

        $result = $this->client($http)->match(
            self::REMOTE_ORIGIN,
            7,
            [5, 3],
            '2026-08-13',
            '2026-08-19',
            [new PhotoTextScheduleEntryDto('Борщ', ['2026-08-13'])],
        );

        $this->assertSame('POST', $http->lastMethod);
        $this->assertSame('/api/food/phototext/schedule/match', $http->lastUrl);
        $this->assertSame(self::REMOTE_ORIGIN, $http->lastBaseUrl);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);
        $this->assertSame([
            'restaurant_id' => 7,
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'entries' => [
                ['name' => 'Борщ', 'dates' => ['2026-08-13']],
            ],
            'category_ids' => [5, 3],
        ], $http->lastJsonBody);
        $this->assertSame(1, $result->matchedCount);
        $this->assertFalse($result->applied);
        $this->assertSame([], $result->categoriesApplied);
        $this->assertSame('2026-08-13', $result->dateFrom);
        $this->assertSame('2026-08-19', $result->dateTo);
        $this->assertSame('Борщ', $result->matched[0]->rawTitle);
        $this->assertSame(101, $result->matched[0]->dishId);
        $this->assertSame('Борщ', $result->matched[0]->dishName);
        $this->assertSame(21, $result->matched[0]->categoryId);
        $this->assertSame('Супы', $result->matched[0]->categoryName);
        $this->assertSame(['2026-08-13'], $result->matched[0]->dates);
        $this->assertSame(PhotoTextMatchIssueCode::DishNotFound, $result->issues[0]->code);
        $this->assertSame('нет в каталоге', $result->issues[0]->message);
        $this->assertSame('Котлета', $result->issues[0]->rawTitle);
    }

    public function test_apply_sends_write_token_and_maps_applied_result(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/schedule/apply' => [
                'status' => 200,
                'body' => $this->schedulePayload(applied: true, categoriesApplied: [21]),
            ],
        ]);

        $result = $this->client($http)->apply(
            self::LOCAL_ORIGIN,
            7,
            [21],
            '2026-08-13',
            '2026-08-19',
            [new PhotoTextScheduleEntryDto('Борщ', ['2026-08-13'])],
        );

        $this->assertSame('/api/food/phototext/schedule/apply', $http->lastUrl);
        $this->assertSame('agent-token-test', $http->lastHeaders['X-PhotoText-Token'] ?? null);
        $this->assertSame('write-token-test', $http->lastHeaders['X-PhotoText-Write-Token'] ?? null);
        $this->assertTrue($result->applied);
        $this->assertSame([21], $result->categoriesApplied);
    }

    public function test_empty_origin_does_not_call_http(): void
    {
        $http = $this->recordingHttpClient([]);

        try {
            $this->client($http)->listRestaurants('   ');
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame('BASE_URL пуст.', $exception->getMessage());
            $this->assertSame(0, $http->callCount);
        }
    }

    public function test_origin_outside_allow_list_does_not_call_http(): void
    {
        $http = $this->recordingHttpClient([]);

        try {
            $this->client($http)->getCatalog('http://169.254.169.254/latest/meta-data', 7);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame(SchedulePhotoOriginAllowList::REJECTION_MESSAGE, $exception->getMessage());
            $this->assertSame(0, $http->callCount);
        }
    }

    public function test_http_401_becomes_domain_exception(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 401,
                'body' => '',
                'successful' => false,
            ],
        ]);

        try {
            $this->client($http)->listRestaurants(self::LOCAL_ORIGIN);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(401, $exception->statusCode());
            $this->assertSame('PhotoText: неверный токен (401).', $exception->getMessage());
        }
    }

    public function test_http_422_becomes_domain_exception_with_message(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/schedule/match' => [
                'status' => 422,
                'body' => ['message' => 'Диапазон графика должен быть ровно 7 дней.'],
                'successful' => false,
            ],
        ]);

        try {
            $this->client($http)->match(
                self::LOCAL_ORIGIN,
                7,
                [21],
                '2026-08-13',
                '2026-08-14',
                [new PhotoTextScheduleEntryDto('Борщ', ['2026-08-13'])],
            );
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(422, $exception->statusCode());
            $this->assertSame('Диапазон графика должен быть ровно 7 дней.', $exception->getMessage());
        }
    }

    public function test_invalid_json_becomes_domain_exception(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/restaurants' => [
                'status' => 200,
                'body' => 'not-json',
            ],
        ]);

        try {
            $this->client($http)->listRestaurants(self::LOCAL_ORIGIN);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(502, $exception->statusCode());
            $this->assertSame('PhotoText: пустой или неверный JSON.', $exception->getMessage());
        }
    }

    public function test_empty_agent_token_does_not_call_http(): void
    {
        $http = $this->recordingHttpClient([]);
        $client = new HttpSchedulePhotoPhotoTextClient(
            $http,
            $this->allowList(),
            '',
            'write-token-test',
            10,
        );

        try {
            $client->listRestaurants(self::LOCAL_ORIGIN);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('agent token', $exception->getMessage());
            $this->assertSame(0, $http->callCount);
        }
    }

    public function test_empty_write_token_blocks_only_apply(): void
    {
        $http = $this->recordingHttpClient([
            '/api/food/phototext/schedule/match' => [
                'status' => 200,
                'body' => $this->schedulePayload(applied: false),
            ],
        ]);
        $client = new HttpSchedulePhotoPhotoTextClient(
            $http,
            $this->allowList(),
            'agent-token-test',
            '',
            10,
        );

        $matched = $client->match(
            self::LOCAL_ORIGIN,
            7,
            null,
            '2026-08-13',
            '2026-08-19',
            [new PhotoTextScheduleEntryDto('Борщ', ['2026-08-13'])],
        );
        $this->assertSame(1, $matched->matchedCount);
        $this->assertArrayNotHasKey('category_ids', $http->lastJsonBody ?? []);
        $this->assertArrayNotHasKey('X-PhotoText-Write-Token', $http->lastHeaders);

        try {
            $client->apply(
                self::LOCAL_ORIGIN,
                7,
                [21],
                '2026-08-13',
                '2026-08-19',
                [new PhotoTextScheduleEntryDto('Борщ', ['2026-08-13'])],
            );
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('write token', $exception->getMessage());
            $this->assertSame(1, $http->callCount);
        }
    }

    public function test_network_error_becomes_domain_exception(): void
    {
        $http = new class implements HttpClientInterface
        {
            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                throw new RuntimeException('Connection refused');
            }
        };

        try {
            $this->client($http)->listRestaurants(self::LOCAL_ORIGIN);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(503, $exception->statusCode());
            $this->assertStringContainsString('Connection refused', $exception->getMessage());
        }
    }

    /**
     * @param  list<int>  $categoriesApplied
     * @return array<string, mixed>
     */
    private function schedulePayload(bool $applied, array $categoriesApplied = []): array
    {
        return [
            'matched_count' => 1,
            'matched' => [
                [
                    'raw_title' => 'Борщ',
                    'dish_id' => 101,
                    'dish_name' => 'Борщ',
                    'category_id' => 21,
                    'category_name' => 'Супы',
                    'dates' => ['2026-08-13'],
                ],
            ],
            'issues' => [
                [
                    'code' => 'dish_not_found',
                    'message' => 'нет в каталоге',
                    'raw_title' => 'Котлета',
                    'dates' => ['2026-08-14'],
                ],
                [
                    'code' => 'unknown_code',
                    'message' => 'skip',
                    'raw_title' => 'skip',
                    'dates' => [],
                ],
            ],
            'date_from' => '2026-08-13',
            'date_to' => '2026-08-19',
            'applied' => $applied,
            'categories_applied' => $categoriesApplied,
        ];
    }

    private function allowList(): SchedulePhotoOriginAllowList
    {
        return new SchedulePhotoOriginAllowList([
            self::LOCAL_ORIGIN,
            self::REMOTE_ORIGIN,
        ]);
    }

    private function client(HttpClientInterface $http): HttpSchedulePhotoPhotoTextClient
    {
        return new HttpSchedulePhotoPhotoTextClient(
            $http,
            $this->allowList(),
            'agent-token-test',
            'write-token-test',
            10,
        );
    }

    /**
     * @param  array<string, array{status: int, body: array<string, mixed>|string, successful?: bool}>  $routes
     * @return object{
     *     lastMethod: string,
     *     lastUrl: string,
     *     lastHeaders: array<string, string>,
     *     lastJsonBody: array<string, mixed>|null,
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

            /** @var array<string, mixed>|null */
            public ?array $lastJsonBody = null;

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
                $this->lastJsonBody = $jsonBody;
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
