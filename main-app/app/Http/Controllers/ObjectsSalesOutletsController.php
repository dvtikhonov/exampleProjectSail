<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObjectsSalesOutletsController extends Controller
{
    // todo заглушка
    /**
     * Заглушка для http://localhost/objects-sales-outlets-2
     */


    public function index(Request $request): Response
    {
        return $this->renderIndex($request, 'ObjectsSalesOutlets/Index', 'objectsSalesOutlets.index');
    }

    public function darkIndex(Request $request): Response
    {
        return $this->renderIndex($request, 'ObjectsSalesOutlets/DarkIndex', 'objectsSalesOutlets.darkIndex');
    }

    private function renderIndex(Request $request, string $component, string $routeName): Response
    {
        $columns = $this->columns();
        $visibleColumns = $this->visibleColumns($request, $columns);
        $columnFilters = $this->columnFilters($request, $columns);
        $filters = [
            'search' => (string) $request->query('search', ''),
            'status' => (string) $request->query('status', ''),
            'column_filters' => $columnFilters,
            'sort' => (string) $request->query('sort', 'id'),
            'direction' => $request->query('direction') === 'desc' ? 'desc' : 'asc',
            'page' => max((int) $request->query('page', 1), 1),
            'per_page' => min(max((int) $request->query('per_page', 10), 5), 50),
            'columns' => $visibleColumns,
        ];

        $salesOutlets = $this->salesOutlets();
        $salesOutlets = $this->filterSalesOutlets($salesOutlets, $filters);
        $salesOutlets = $this->sortSalesOutlets($salesOutlets, $filters['sort'], $filters['direction']);

        $total = count($salesOutlets);
        $lastPage = max((int) ceil($total / $filters['per_page']), 1);
        $currentPage = min($filters['page'], $lastPage);
        $offset = ($currentPage - 1) * $filters['per_page'];
        $rows = $this->normalizeRows(
            array_slice($salesOutlets, $offset, $filters['per_page']),
            $columns,
        );

        return Inertia::render($component, [
            'columns' => $columns,
            'salesOutlets' => array_values($rows),
            'filters' => array_merge($filters, ['page' => $currentPage]),
            'pagination' => [
                'current_page' => $currentPage,
                'last_page' => $lastPage,
                'per_page' => $filters['per_page'],
                'total' => $total,
                'from' => $total === 0 ? 0 : $offset + 1,
                'to' => min($offset + count($rows), $total),
            ],
            'statusOptions' => [
                ['value' => '', 'label' => 'Все статусы'],
                ['value' => 'approved', 'label' => 'Одобрено'],
                ['value' => 'review', 'label' => 'На проверке'],
                ['value' => 'blocked', 'label' => 'Есть изменения'],
            ],
            'routes' => [
                'index' => route($routeName),
            ],
        ]);
    }

    private function columns(): array
    {
        return [
            ['key' => 'id', 'label' => 'ID объекта продаж', 'sortable' => true, 'width' => 120, 'align' => 'center'],
            ['key' => 'shop', 'label' => 'Магазин', 'sortable' => true, 'width' => 150, 'align' => 'center'],
            ['key' => 'manager', 'label' => 'Менеджер', 'sortable' => true, 'width' => 190, 'align' => 'center'],
            ['key' => 'curator', 'label' => 'Куратор ТТ', 'sortable' => true, 'width' => 190, 'align' => 'center'],
            ['key' => 'name', 'label' => 'Название ТТ', 'sortable' => true, 'width' => 170, 'align' => 'center'],
            ['key' => 'inn', 'label' => 'ИНН головной', 'sortable' => true, 'width' => 170, 'align' => 'center'],
            ['key' => 'head_organization', 'label' => 'Головная организация', 'sortable' => true, 'width' => 260, 'align' => 'center', 'cellType' => 'headOrganizationPoptip'],
            ['key' => 'head_organization1', 'label' => 'Головная организация1', 'sortable' => true, 'width' => 260, 'align' => 'center', 'cellType' => 'headOrganizationPoptip'],
            ['key' => 'organization_name', 'label' => 'Название организации', 'sortable' => true, 'width' => 260, 'align' => 'center'],
            ['key' => 'status_label', 'label' => 'Статус', 'sortable' => true, 'width' => 170, 'align' => 'center', 'cellType' => 'statusBadge'],
            ['key' => 'approved', 'label' => 'Одобрено', 'sortable' => true, 'width' => 140, 'align' => 'center'],
        ];
    }

    private function visibleColumns(Request $request, array $columns): array
    {
        $availableKeys = array_column($columns, 'key');
        $requestedColumns = $request->query('columns');

        if (! is_array($requestedColumns)) {
            return $availableKeys;
        }

        $visibleColumns = array_values(array_intersect($requestedColumns, $availableKeys));

        return $visibleColumns === [] ? $availableKeys : $visibleColumns;
    }

    private function columnFilters(Request $request, array $columns): array
    {
        $availableKeys = array_column($columns, 'key');
        $requestedFilters = $request->query('column_filters', []);

        if (! is_array($requestedFilters)) {
            return [];
        }

        $filters = [];

        foreach ($availableKeys as $key) {
            $value = trim((string) ($requestedFilters[$key] ?? ''));

            if ($value !== '') {
                $filters[$key] = $value;
            }
        }

        return $filters;
    }

    private function normalizeRows(array $rows, array $columns): array
    {
        $emptyColumnValues = array_fill_keys(array_column($columns, 'key'), '');

        return array_map(
            fn (array $row): array => array_merge($emptyColumnValues, $row),
            $rows,
        );
    }

    private function filterSalesOutlets(array $salesOutlets, array $filters): array
    {
        $search = mb_strtolower(trim($filters['search']));
        $status = $filters['status'];
        $columnFilters = $filters['column_filters'];

        return array_values(array_filter($salesOutlets, function (array $salesOutlet) use ($search, $status, $columnFilters): bool {
            if ($status !== '' && $salesOutlet['status'] !== $status) {
                return false;
            }

            foreach ($columnFilters as $key => $value) {
                $filterValue = mb_strtolower($value);
                $cellValue = mb_strtolower((string) ($salesOutlet[$key] ?? ''));

                if (! str_contains($cellValue, $filterValue)) {
                    return false;
                }
            }

            if ($search === '') {
                return true;
            }

            $haystack = mb_strtolower(implode(' ', [
                $salesOutlet['id'],
                $salesOutlet['shop'],
                $salesOutlet['manager'],
                $salesOutlet['curator'],
                $salesOutlet['name'],
                $salesOutlet['inn'],
                $salesOutlet['head_organization'],
                $salesOutlet['organization_name'],
            ]));

            return str_contains($haystack, $search);
        }));
    }

    private function sortSalesOutlets(array $salesOutlets, string $sort, string $direction): array
    {
        $allowedSorts = array_column($this->columns(), 'key');

        if (! in_array($sort, $allowedSorts, true)) {
            return $salesOutlets;
        }

        usort($salesOutlets, function (array $first, array $second) use ($sort, $direction): int {
            $result = strnatcasecmp((string) ($first[$sort] ?? ''), (string) ($second[$sort] ?? ''));

            return $direction === 'desc' ? -$result : $result;
        });

        return $salesOutlets;
    }

    private function salesOutlets(): array
    {
        return [
            [
                'id' => 1001,
                'shop' => 'Белгород',
                'manager' => 'Брюхненко С. Ю.',
                'curator' => 'Коршунов М. С.',
                'name' => 'Колхоз',
                'inn' => '311300065310',
                'head_organization' => 'ИП Рудев Востин Минович',
                'organization_name' => 'ИП Рудев Востин Игорь Васильевич',
                'status' => 'blocked',
                'status_label' => 'Есть изменения',
                'approved' => 'Нет',
                'row_tone' => 'danger',
            ],
            [
                'id' => 1002,
                'shop' => 'Белгород',
                'manager' => 'Брюхненко С. Ю.',
                'curator' => 'Коршунов М. С.',
                'name' => 'Колхоз',
                'inn' => '311800277237',
                'head_organization' => 'ИП Майгатов Любовь Александровна',
                'organization_name' => 'ИП Майгатова Любовь Александровна',
                'status' => 'blocked',
                'status_label' => 'Есть изменения',
                'approved' => 'Нет',
                'row_tone' => 'danger',
            ],
            [
                'id' => 1003,
                'shop' => 'Воронеж',
                'manager' => 'Морозова Е. А.',
                'curator' => 'Поляков А. В.',
                'name' => 'Северный',
                'inn' => '3662111223',
                'head_organization' => 'ООО Северный продукт',
                'organization_name' => 'ООО Северный продукт',
                'status' => 'review',
                'status_label' => 'На проверке',
                'approved' => 'Частично',
                'row_tone' => 'warning',
            ],
            [
                'id' => 1004,
                'shop' => 'Курск',
                'manager' => 'Семенов И. П.',
                'curator' => 'Лебедева А. Н.',
                'name' => 'Центральный',
                'inn' => '4632014589',
                'head_organization' => 'ООО Центральная сеть',
                'organization_name' => 'ООО Центральная сеть',
                'status' => 'approved',
                'status_label' => 'Одобрено',
                'approved' => 'Да',
                'row_tone' => 'success',
            ],
            [
                'id' => 1005,
                'shop' => 'Липецк',
                'manager' => 'Иванова М. А.',
                'curator' => 'Андреев К. О.',
                'name' => 'Южный',
                'inn' => '4825098765',
                'head_organization' => 'АО Южная торговля',
                'organization_name' => 'АО Южная торговля',
                'status' => 'review',
                'status_label' => 'На проверке',
                'approved' => 'Частично',
                'row_tone' => 'warning',
            ],
            [
                'id' => 1006,
                'shop' => 'Орел',
                'manager' => 'Кузнецов Р. Д.',
                'curator' => 'Петрова Л. С.',
                'name' => 'Партнер',
                'inn' => '5753123456',
                'head_organization' => 'ИП Ковалев Сергей Петрович',
                'organization_name' => 'ИП Ковалев Сергей Петрович',
                'status' => 'approved',
                'status_label' => 'Одобрено',
                'approved' => 'Да',
                'row_tone' => 'success',
            ],
            [
                'id' => 1007,
                'shop' => 'Тамбов',
                'manager' => 'Громова Н. И.',
                'curator' => 'Смирнов Д. Е.',
                'name' => 'Оптима',
                'inn' => '6829012345',
                'head_organization' => 'ООО Оптима маркет',
                'organization_name' => 'ООО Оптима маркет',
                'status' => 'blocked',
                'status_label' => 'Есть изменения',
                'approved' => 'Нет',
                'row_tone' => 'danger',
            ],
            [
                'id' => 1008,
                'shop' => 'Старый Оскол',
                'manager' => 'Фролов П. С.',
                'curator' => 'Никитина Ю. В.',
                'name' => 'Фермер',
                'inn' => '3128012345',
                'head_organization' => 'СПК Фермер',
                'organization_name' => 'СПК Фермер',
                'status' => 'approved',
                'status_label' => 'Одобрено',
                'approved' => 'Да',
                'row_tone' => 'success',
            ],
        ];
    }
}
