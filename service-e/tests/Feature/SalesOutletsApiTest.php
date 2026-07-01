<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Entity\SalesOutlet;
use App\Response\GatewayUnauthorizedResponse;
use App\Tests\Support\SalesOutletTestSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Feature-тесты REST API /api/sales-outlets (совместимость с service-a). */
class SalesOutletsApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    private int $gatewayUserId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        SalesOutletTestSeeder::seed($this->entityManager);
    }

    public function testItReturnsUnauthorizedWithoutGatewayHeader(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets');

        self::assertSame(401, $response['status']);
        self::assertSame(GatewayUnauthorizedResponse::MESSAGE, $response['body']['message']);
    }

    public function testItReturnsSalesOutletsList(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets', gatewayUser: true);

        self::assertSame(200, $response['status']);
        self::assertSame(8, $response['body']['meta']['pagination']['total']);
        self::assertSame(1001, $response['body']['data'][0]['id']);
        self::assertSame('Есть изменения', $response['body']['data'][0]['status_label']);
        self::assertSame('danger', $response['body']['data'][0]['row_tone']);
        self::assertSame('ip', $response['body']['data'][0]['head_organization_type']);
        self::assertSame('ИП', $response['body']['data'][0]['head_organization_type_label']);
    }

    public function testItFiltersByStatus(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets?status=approved', gatewayUser: true);

        self::assertSame(200, $response['status']);
        self::assertSame(3, $response['body']['meta']['pagination']['total']);
        self::assertSame('approved', $response['body']['data'][0]['status']);
    }

    public function testItSearchesSalesOutlets(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets?search=Фермер', gatewayUser: true);

        self::assertSame(200, $response['status']);
        self::assertSame(1, $response['body']['meta']['pagination']['total']);
        self::assertSame(1008, $response['body']['data'][0]['id']);
    }

    public function testItAppliesColumnFilters(): void
    {
        $response = $this->requestJson(
            'GET',
            '/api/sales-outlets?column_filters[shop]=Белгород&column_filters[inn]=3118',
            gatewayUser: true,
        );

        self::assertSame(200, $response['status']);
        self::assertSame(1, $response['body']['meta']['pagination']['total']);
        self::assertSame(1002, $response['body']['data'][0]['id']);
    }

    public function testItSortsSalesOutlets(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets?sort=shop&direction=desc', gatewayUser: true);

        self::assertSame(200, $response['status']);
        self::assertSame('Тамбов', $response['body']['data'][0]['shop']);
        self::assertSame('Старый Оскол', $response['body']['data'][1]['shop']);
    }

    public function testItLimitsPerPage(): void
    {
        $response = $this->requestJson('GET', '/api/sales-outlets?per_page=2&page=2', gatewayUser: true);

        self::assertSame(200, $response['status']);
        self::assertCount(3, $response['body']['data']);
        self::assertSame(5, $response['body']['meta']['filters']['per_page']);
        self::assertSame(2, $response['body']['meta']['pagination']['current_page']);
        self::assertSame(6, $response['body']['meta']['pagination']['from']);
    }

    public function testItValidatesSalesOutletsIndexQuery(): void
    {
        $response = $this->requestJson(
            'GET',
            '/api/sales-outlets?status=archived&sort=unknown&direction=sideways&columns[]=bad',
            gatewayUser: true,
        );

        self::assertSame(422, $response['status']);
        $this->assertJsonValidationErrors($response['body'], ['status', 'sort', 'direction', 'columns.0']);
    }

    public function testItUpdatesHeadOrganization(): void
    {
        $userId = $this->gatewayUserId;

        $response = $this->requestJson(
            'POST',
            '/api/sales-outlets/1001/head-organization',
            [
                'head_organization' => 'Новая головная организация',
                'head_organization_type' => 'АО',
            ],
            gatewayUser: true,
        );

        self::assertSame(200, $response['status']);
        self::assertSame(1001, $response['body']['id']);
        self::assertSame('Новая головная организация', $response['body']['head_organization']);
        self::assertSame('ao', $response['body']['head_organization_type']);
        self::assertSame('АО', $response['body']['head_organization_type_label']);
        self::assertSame($userId, $response['body']['user_id']);

        $this->assertDatabaseHasSalesOutlet([
            'id' => 1001,
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ao',
            'user_id' => $userId,
        ]);
    }

    public function testItUpdatesSalesOutlet(): void
    {
        $userId = $this->gatewayUserId;

        $response = $this->requestJson(
            'PATCH',
            '/api/sales-outlets/1001',
            [
                'shop' => 'Воронеж',
                'manager' => 'Новый менеджер',
                'curator' => 'Новый куратор',
                'name' => 'Обновленная точка',
                'inn' => '3666123456',
                'head_organization' => 'Новая головная организация',
                'head_organization_type' => 'ООО',
                'organization_name' => 'ООО Новая организация',
                'status' => 'approved',
                'id' => 999999,
                'row_tone' => 'danger',
                'approved' => 'Нет',
            ],
            gatewayUser: true,
        );

        self::assertSame(200, $response['status']);
        self::assertSame(1001, $response['body']['id']);
        self::assertSame('Воронеж', $response['body']['shop']);
        self::assertSame('Новый менеджер', $response['body']['manager']);
        self::assertSame('Новый куратор', $response['body']['curator']);
        self::assertSame('Обновленная точка', $response['body']['name']);
        self::assertSame('3666123456', $response['body']['inn']);
        self::assertSame('Новая головная организация', $response['body']['head_organization']);
        self::assertSame('ooo', $response['body']['head_organization_type']);
        self::assertSame('ООО', $response['body']['head_organization_type_label']);
        self::assertSame('ООО Новая организация', $response['body']['organization_name']);
        self::assertSame('approved', $response['body']['status']);
        self::assertSame('Одобрено', $response['body']['status_label']);
        self::assertSame($userId, $response['body']['user_id']);
        self::assertSame('success', $response['body']['row_tone']);

        $this->assertDatabaseHasSalesOutlet([
            'id' => 1001,
            'shop' => 'Воронеж',
            'manager' => 'Новый менеджер',
            'curator' => 'Новый куратор',
            'name' => 'Обновленная точка',
            'inn' => '3666123456',
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ooo',
            'organization_name' => 'ООО Новая организация',
            'status' => 'approved',
            'user_id' => $userId,
        ]);

        self::assertNull($this->entityManager->getRepository(SalesOutlet::class)->find(999999));
    }

    public function testItValidatesSalesOutletUpdate(): void
    {
        $response = $this->requestJson(
            'PATCH',
            '/api/sales-outlets/1001',
            [
                'shop' => '',
                'manager' => '',
                'curator' => '',
                'name' => '',
                'inn' => 'bad-inn',
                'head_organization' => '',
                'head_organization_type' => 'ЗАО',
                'organization_name' => '',
                'status' => 'archived',
            ],
            gatewayUser: true,
        );

        self::assertSame(422, $response['status']);
        $this->assertJsonValidationErrors($response['body'], [
            'shop',
            'manager',
            'curator',
            'name',
            'inn',
            'head_organization',
            'head_organization_type',
            'organization_name',
            'status',
        ]);
    }

    public function testItReturnsNotFoundForUnknownSalesOutletOnUpdate(): void
    {
        $response = $this->requestJson(
            'PATCH',
            '/api/sales-outlets/999999',
            $this->updateSalesOutletData(),
            gatewayUser: true,
        );

        self::assertSame(404, $response['status']);
    }

    public function testItSoftDeletesSalesOutletAndStoresLastUser(): void
    {
        $userId = $this->gatewayUserId;

        $response = $this->requestJson('DELETE', '/api/sales-outlets/1001', gatewayUser: true);

        self::assertSame(204, $response['status']);
        $this->assertSoftDeletedSalesOutlet(1001, $userId);
    }

    public function testItAutomaticallyStoresGatewayUserHeaderOnSalesOutletUpdate(): void
    {
        $userId = $this->gatewayUserId;

        $response = $this->requestJson(
            'POST',
            '/api/sales-outlets/1001/head-organization',
            [
                'head_organization' => 'Головная через gateway',
                'head_organization_type' => 'ООО',
            ],
            gatewayUser: true,
        );

        self::assertSame(200, $response['status']);
        self::assertSame($userId, $response['body']['user_id']);

        $this->assertDatabaseHasSalesOutlet([
            'id' => 1001,
            'head_organization' => 'Головная через gateway',
            'user_id' => $userId,
        ]);
    }

    public function testItValidatesHeadOrganizationUpdate(): void
    {
        $response = $this->requestJson(
            'POST',
            '/api/sales-outlets/1001/head-organization',
            [
                'head_organization' => '',
                'head_organization_type' => 'ЗАО',
            ],
            gatewayUser: true,
        );

        self::assertSame(422, $response['status']);
        $this->assertJsonValidationErrors($response['body'], ['head_organization', 'head_organization_type']);
    }

    public function testItReturnsNotFoundForUnknownSalesOutletOnHeadOrganizationUpdate(): void
    {
        $response = $this->requestJson(
            'POST',
            '/api/sales-outlets/999999/head-organization',
            [
                'head_organization' => 'Новая головная организация',
                'head_organization_type' => 'ООО',
            ],
            gatewayUser: true,
        );

        self::assertSame(404, $response['status']);
    }

    /**
     * @param array<string, mixed>|null $payload
     *
     * @return array{status: int, body: array<string, mixed>|null}
     */
    private function requestJson(
        string $method,
        string $uri,
        ?array $payload = null,
        bool $gatewayUser = false,
    ): array {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($gatewayUser) {
            $server['HTTP_X-User-Id'] = (string) $this->gatewayUserId;
        }

        $this->client->request(
            $method,
            $uri,
            server: $server,
            content: null === $payload ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        );

        $httpResponse = $this->client->getResponse();
        $content = $httpResponse->getContent();

        return [
            'status' => $httpResponse->getStatusCode(),
            'body' => false === $content || '' === $content ? null : json_decode($content, true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string>         $fields
     */
    private function assertJsonValidationErrors(array $body, array $fields): void
    {
        self::assertArrayHasKey('errors', $body);

        foreach ($fields as $field) {
            self::assertArrayHasKey($field, $body['errors'], sprintf('Missing validation error for [%s]', $field));
        }
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function assertDatabaseHasSalesOutlet(array $attributes): void
    {
        $id = (int) $attributes['id'];
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT * FROM sales_outlets WHERE id = :id',
            ['id' => $id],
        );

        self::assertIsArray($row, sprintf('Sales outlet [%d] was not found.', $id));

        foreach ($attributes as $column => $expected) {
            self::assertSame((string) $expected, (string) $row[$column], sprintf('Mismatch for column [%s]', $column));
        }
    }

    private function assertSoftDeletedSalesOutlet(int $id, int $userId): void
    {
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'SELECT deleted_at, user_id FROM sales_outlets WHERE id = :id',
            ['id' => $id],
        );

        self::assertIsArray($row);
        self::assertNotNull($row['deleted_at']);
        self::assertSame($userId, (int) $row['user_id']);
    }

    /**
     * @return array<string, string>
     */
    private function updateSalesOutletData(): array
    {
        return [
            'shop' => 'Воронеж',
            'manager' => 'Новый менеджер',
            'curator' => 'Новый куратор',
            'name' => 'Обновленная точка',
            'inn' => '3666123456',
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ООО',
            'organization_name' => 'ООО Новая организация',
            'status' => 'approved',
        ];
    }
}
