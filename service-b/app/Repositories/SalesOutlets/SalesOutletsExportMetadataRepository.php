<?php

namespace App\Repositories\SalesOutlets;

class SalesOutletsExportMetadataRepository implements SalesOutletsExportMetadataRepositoryInterface
{
    /**
     * @var array<int, array<string, bool|int|string>>
     */
    private const COLUMNS = [
        ['key' => 'id', 'label' => 'ID объекта продаж', 'sortable' => true],
        ['key' => 'shop', 'label' => 'Магазин', 'sortable' => true],
        ['key' => 'manager', 'label' => 'Менеджер', 'sortable' => true],
        ['key' => 'curator', 'label' => 'Куратор ТТ', 'sortable' => true],
        ['key' => 'name', 'label' => 'Название ТТ', 'sortable' => true],
        ['key' => 'inn', 'label' => 'ИНН головной', 'sortable' => true],
        ['key' => 'head_organization', 'label' => 'Головная организация', 'sortable' => true],
        ['key' => 'head_organization_type', 'label' => 'Вид', 'sortable' => true],
        ['key' => 'organization_name', 'label' => 'Название организации', 'sortable' => true],
        ['key' => 'status_label', 'label' => 'Статус', 'sortable' => true],
        ['key' => 'approved', 'label' => 'Одобрено', 'sortable' => true],
        ['key' => 'user_id', 'label' => 'Последний пользователь', 'sortable' => true],
    ];

    public function columns(): array
    {
        return self::COLUMNS;
    }

    public function allowedColumnKeys(): array
    {
        return array_column(self::COLUMNS, 'key');
    }
}
