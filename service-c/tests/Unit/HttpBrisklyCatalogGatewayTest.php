<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Shared\HttpClientInterface;
use App\DTO\Food\BrisklySync\BrisklyCreatedItemDto;
use App\DTO\Shared\HttpResponseDto;
use App\Exceptions\Food\FoodDomainException;
use App\Infrastructure\Briskly\HttpBrisklyCatalogGateway;
use PHPUnit\Framework\TestCase;

/**
 * Контракт payload CREATE и текст ошибок Briskly HTTP.
 */
final class HttpBrisklyCatalogGatewayTest extends TestCase
{
    public function test_create_item_sends_okei_piece_unit_id(): void
    {
        $http = new class implements HttpClientInterface
        {
            public string $lastMethod = '';

            public string $lastUrl = '';

            /** @var array<string, mixed>|null */
            public ?array $lastBody = null;

            public function request(
                string $method,
                string $url,
                array $headers = [],
                ?array $jsonBody = null,
                ?string $baseUrl = null,
                int $timeoutSeconds = 30,
            ): HttpResponseDto {
                $this->lastMethod = $method;
                $this->lastUrl = $url;
                $this->lastBody = $jsonBody;

                $body = json_encode([
                    'id' => 555001,
                    'name' => 'Сельдь под шубой',
                    'price' => 65,
                    'barcode' => '2000033437482',
                    'category_id' => 48138,
                    'catalog_id' => 12,
                    'status' => 1,
                    'unit_id' => 796,
                ], JSON_THROW_ON_ERROR);

                return new HttpResponseDto(status: 200, body: $body, successful: true);
            }
        };

        $gateway = new HttpBrisklyCatalogGateway(
            $http,
            'https://briskly.example/api/company',
            30,
            100,
            1,
            0,
        );

        $created = $gateway->createItem('token', 'Сельдь под шубой', '65.00', 48138, 12);

        $this->assertSame('POST', $http->lastMethod);
        $this->assertSame('/v1/dashboard/item/create', $http->lastUrl);
        $this->assertIsArray($http->lastBody);
        $this->assertSame(796, $http->lastBody['unit_id'] ?? null);
        $this->assertSame('Сельдь под шубой', $http->lastBody['name'] ?? null);
        $this->assertSame(65.0, $http->lastBody['price'] ?? null);
        $this->assertSame(48138, $http->lastBody['category_id'] ?? null);
        $this->assertSame(12, $http->lastBody['catalog_id'] ?? null);
        $this->assertSame('generate', $http->lastBody['barcode'] ?? null);
        $this->assertSame(0, $http->lastBody['parent_id'] ?? null);
        $this->assertSame('', $http->lastBody['text'] ?? null);
        $this->assertArrayNotHasKey('id', $http->lastBody);

        $this->assertInstanceOf(BrisklyCreatedItemDto::class, $created);
        $this->assertSame(555001, $created->id);
        $this->assertSame('Сельдь под шубой', $created->name);
        $this->assertSame('65.00', $created->price);
        $this->assertSame('2000033437482', $created->barcode);
        $this->assertSame(48138, $created->categoryId);
        $this->assertSame(12, $created->catalogId);
        $this->assertSame(1, $created->status);
        $this->assertSame(796, $created->unitId);
    }

    public function test_create_item_without_id_throws_domain_exception(): void
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
                return new HttpResponseDto(
                    status: 200,
                    body: '{"name":"X","price":10}',
                    successful: true,
                );
            }
        };

        $gateway = new HttpBrisklyCatalogGateway(
            $http,
            'https://briskly.example/api/company',
            30,
            100,
            1,
            0,
        );

        try {
            $gateway->createItem('token', 'X', '10.00', 1, 1);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertSame(502, $exception->statusCode());
            $this->assertStringContainsString('не содержит id', $exception->getMessage());
        }
    }

    public function test_request_error_includes_briskly_message_body(): void
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
                return new HttpResponseDto(
                    status: 400,
                    body: '{"message":"unit_id is invalid"}',
                    successful: false,
                );
            }
        };

        $gateway = new HttpBrisklyCatalogGateway(
            $http,
            'https://briskly.example/api/company',
            30,
            100,
            1,
            0,
        );

        try {
            $gateway->createItem('token', 'X', '10.00', 1, 1);
            $this->fail('Ожидался FoodDomainException');
        } catch (FoodDomainException $exception) {
            $this->assertStringContainsString('HTTP 400', $exception->getMessage());
            $this->assertStringContainsString('/v1/dashboard/item/create', $exception->getMessage());
            $this->assertStringContainsString('unit_id is invalid', $exception->getMessage());
        }
    }
}
