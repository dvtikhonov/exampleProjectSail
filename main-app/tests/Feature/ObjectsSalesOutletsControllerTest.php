<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleAuthPassport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ObjectsSalesOutletsControllerTest extends TestCase
{
    public function test_objects_sales_outlets_2_uses_service_a_payload(): void
    {
        Http::fake([
            'http://gateway/api/a/sales-outlets*' => Http::response($this->servicePayload(), 200),
        ]);

        $response = $this
            ->withoutMiddleware(HandleAuthPassport::class)
            ->get('/objects-sales-outlets-2?status=approved&sort=shop&direction=desc&page=2&per_page=25');

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ObjectsSalesOutlets/DarkIndex')
                ->where('salesOutlets.0.id', 1004)
                ->where('columns.0.key', 'id')
                ->where('filters.status', 'approved')
                ->where('filters.sort', 'shop')
                ->where('pagination.current_page', 2)
                ->where('statusOptions.1.value', 'approved')
                ->where('routes.index', route('objectsSalesOutlets.darkIndex'))
            );

        Http::assertSent(function (Request $request): bool {
            $query = $this->requestQuery($request);

            return $this->requestUrl($request) === 'http://gateway/api/a/sales-outlets'
                && $query['status'] === 'approved'
                && $query['sort'] === 'shop'
                && $query['direction'] === 'desc'
                && $query['page'] === '2'
                && $query['per_page'] === '25';
        });
    }

    public function test_objects_sales_outlets_passes_columns_and_column_filters_to_service_a(): void
    {
        Http::fake([
            'http://gateway/api/a/sales-outlets*' => Http::response($this->servicePayload(), 200),
        ]);

        $this
            ->withoutMiddleware(HandleAuthPassport::class)
            ->get('/objects-sales-outlets?columns[]=id&columns[]=shop&column_filters[shop]=Белгород&column_filters[inn]=3118')
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('ObjectsSalesOutlets/Index')
                ->where('routes.index', route('objectsSalesOutlets.index'))
            );

        Http::assertSent(function (Request $request): bool {
            $query = $this->requestQuery($request);

            return $this->requestUrl($request) === 'http://gateway/api/a/sales-outlets'
                && $query['columns'] === ['id', 'shop']
                && $query['column_filters'] === [
                    'shop' => 'Белгород',
                    'inn' => '3118',
                ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(): array
    {
        return [
            'data' => [
                [
                    'id' => 1004,
                    'shop' => 'Курск',
                    'manager' => 'Семенов И. П.',
                    'curator' => 'Лебедева А. Н.',
                    'name' => 'Центральный',
                    'inn' => '4632014589',
                    'head_organization' => 'ООО Центральная сеть',
                    'head_organization_type' => 'ooo',
                    'head_organization_type_label' => 'ООО',
                    'organization_name' => 'ООО Центральная сеть',
                    'status' => 'approved',
                    'status_label' => 'Одобрено',
                    'approved' => 'Да',
                    'row_tone' => 'success',
                ],
            ],
            'meta' => [
                'columns' => [
                    ['key' => 'id', 'label' => 'ID объекта продаж', 'sortable' => true],
                    ['key' => 'shop', 'label' => 'Магазин', 'sortable' => true],
                ],
                'filters' => [
                    'search' => '',
                    'status' => 'approved',
                    'column_filters' => [],
                    'sort' => 'shop',
                    'direction' => 'desc',
                    'page' => 2,
                    'per_page' => 25,
                    'columns' => ['id', 'shop'],
                ],
                'pagination' => [
                    'current_page' => 2,
                    'last_page' => 3,
                    'per_page' => 25,
                    'total' => 60,
                    'from' => 26,
                    'to' => 50,
                ],
                'status_options' => [
                    ['value' => '', 'label' => 'Все статусы'],
                    ['value' => 'approved', 'label' => 'Одобрено'],
                ],
            ],
        ];
    }

    private function requestUrl(Request $request): string
    {
        return strtok($request->url(), '?');
    }

    /**
     * @return array<string, mixed>
     */
    private function requestQuery(Request $request): array
    {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?: '', $query);

        return $query;
    }
}
