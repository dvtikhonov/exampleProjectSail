<?php

namespace Tests\Feature;

use Database\Seeders\SalesOutletSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $response = $this->postJson('/api/sales-outlets/1001/head-organization', [
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'АО',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('id', 1001)
            ->assertJsonPath('head_organization', 'Новая головная организация')
            ->assertJsonPath('head_organization_type', 'ao')
            ->assertJsonPath('head_organization_type_label', 'АО');

        $this->assertDatabaseHas('sales_outlets', [
            'id' => 1001,
            'head_organization' => 'Новая головная организация',
            'head_organization_type' => 'ao',
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
}
