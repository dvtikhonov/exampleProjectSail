<?php

namespace Tests\Feature;

use App\Models\SalesOutlet;
use Database\Seeders\SalesOutletSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Tests\TestCase;

class SalesOutletsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SalesOutletSeeder::class);
    }

    public function test_it_returns_sales_outlets_list(): void
    {
        $response = $this->getJson('/api/sales-outlets');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 8)
            ->assertJsonPath('data.0.id', 1001)
            ->assertJsonPath('data.0.status_label', 'Есть изменения')
            ->assertJsonPath('data.0.row_tone', 'danger')
            ->assertJsonPath('data.0.head_organization_type', 'ip')
            ->assertJsonPath('data.0.head_organization_type_label', 'ИП');
    }

    public function test_it_filters_by_status(): void
    {
        $response = $this->getJson('/api/sales-outlets?status=approved');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 3)
            ->assertJsonPath('data.0.status', 'approved');
    }

    public function test_it_searches_sales_outlets(): void
    {
        $response = $this->getJson('/api/sales-outlets?search=Фермер');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', 1008);
    }

    public function test_it_applies_column_filters(): void
    {
        $response = $this->getJson('/api/sales-outlets?column_filters[shop]=Белгород&column_filters[inn]=3118');

        $response
            ->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', 1002);
    }

    public function test_it_sorts_sales_outlets(): void
    {
        $response = $this->getJson('/api/sales-outlets?sort=shop&direction=desc');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.shop', 'Тамбов')
            ->assertJsonPath('data.1.shop', 'Старый Оскол');
    }

    public function test_it_limits_per_page(): void
    {
        $response = $this->getJson('/api/sales-outlets?per_page=2&page=2');

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.filters.per_page', 5)
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.from', 6);
    }

    public function test_it_updates_head_organization(): void
    {
        $userId = 12345;

        $response = $this
            ->withHeader('X-User-Id', (string) $userId)
            ->postJson('/api/sales-outlets/1001/head-organization', [
                'head_organization' => 'Новая головная организация',
                'head_organization_type' => 'АО',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('id', 1001)
            ->assertJsonPath('head_organization', 'Новая головная организация')
            ->assertJsonPath('head_organization_type', 'ao')
            ->assertJsonPath('head_organization_type_label', 'АО')
            ->assertJsonPath('user_id', $userId);

        $this->assertDatabaseHas('sales_outlets', [
            'id' => 1001,
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ao',
            'user_id' => $userId,
        ]);
    }

    public function test_it_updates_sales_outlet(): void
    {
        $userId = 12345;

        $response = $this
            ->withHeader('X-User-Id', (string) $userId)
            ->patchJson('/api/sales-outlets/1001', [
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
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('id', 1001)
            ->assertJsonPath('shop', 'Воронеж')
            ->assertJsonPath('manager', 'Новый менеджер')
            ->assertJsonPath('curator', 'Новый куратор')
            ->assertJsonPath('name', 'Обновленная точка')
            ->assertJsonPath('inn', '3666123456')
            ->assertJsonPath('head_organization', 'Новая головная организация')
            ->assertJsonPath('head_organization_type', 'ooo')
            ->assertJsonPath('head_organization_type_label', 'ООО')
            ->assertJsonPath('organization_name', 'ООО Новая организация')
            ->assertJsonPath('status', 'approved')
            ->assertJsonPath('status_label', 'Одобрено')
            ->assertJsonPath('user_id', $userId)
            ->assertJsonPath('row_tone', 'success');

        $this->assertDatabaseHas('sales_outlets', [
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

        $this->assertDatabaseMissing('sales_outlets', [
            'id' => 999999,
        ]);
    }

    public function test_it_validates_sales_outlet_update(): void
    {
        $response = $this->patchJson('/api/sales-outlets/1001', [
            'shop' => '',
            'manager' => '',
            'curator' => '',
            'name' => '',
            'inn' => 'bad-inn',
            'head_organization' => '',
            'head_organization_type' => 'ЗАО',
            'organization_name' => '',
            'status' => 'archived',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
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

    public function test_it_returns_not_found_for_unknown_sales_outlet_on_update(): void
    {
        $this->patchJson('/api/sales-outlets/999999', $this->updateSalesOutletData())
            ->assertNotFound();
    }

    public function test_it_soft_deletes_sales_outlet_and_stores_last_user(): void
    {
        $userId = 12345;

        $this
            ->withHeader('X-User-Id', (string) $userId)
            ->deleteJson('/api/sales-outlets/1001')
            ->assertNoContent();

        $this->assertSoftDeleted('sales_outlets', [
            'id' => 1001,
            'user_id' => $userId,
        ]);
    }

    public function test_it_automatically_stores_gateway_user_header_on_sales_outlet_create(): void
    {
        $userId = 12345;

        request()->attributes->set('gateway_user_id', $userId);

        $salesOutlet = SalesOutlet::query()->create($this->newSalesOutletData());

        $this->assertDatabaseHas('sales_outlets', [
            'id' => $salesOutlet->id,
            'user_id' => $userId,
        ]);
    }

    public function test_it_automatically_stores_gateway_user_header_on_sales_outlet_update(): void
    {
        $userId = 12345;

        $response = $this
            ->withHeader('X-User-Id', (string) $userId)
            ->postJson('/api/sales-outlets/1001/head-organization', [
                'head_organization' => 'Головная через gateway',
                'head_organization_type' => 'ООО',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('user_id', $userId);

        $this->assertDatabaseHas('sales_outlets', [
            'id' => 1001,
            'head_organization' => 'Головная через gateway',
            'user_id' => $userId,
        ]);
    }

    public function test_it_validates_head_organization_update(): void
    {
        $response = $this->postJson('/api/sales-outlets/1001/head-organization', [
            'head_organization' => '',
            'head_organization_type' => 'ЗАО',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['head_organization', 'head_organization_type']);
    }

    public function test_it_returns_not_found_for_unknown_sales_outlet_on_head_organization_update(): void
    {
        $this->postJson('/api/sales-outlets/999999/head-organization', [
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ООО',
        ])->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function newSalesOutletData(): array
    {
        return [
            'shop' => 'Москва',
            'manager' => 'Иванов И. И.',
            'curator' => 'Петров П. П.',
            'name' => 'Новая точка',
            'inn' => '7701234567',
            'head_organization' => 'ООО Новая точка',
            'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
            'organization_name' => 'ООО Новая точка',
            'status' => SalesOutletStatus::Review,
            'approved' => 'Частично',
        ];
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
