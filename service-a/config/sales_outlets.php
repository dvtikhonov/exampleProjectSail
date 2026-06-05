<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI-настройки колонок таблицы (merge поверх shared SalesOutletColumns)
    |--------------------------------------------------------------------------
    |
    | Ключ — column key из Shared\SalesOutletsDomain\Metadata\SalesOutletColumns.
    | Поддерживаемые поля: width (int), align (left|center|right), cellType (text|statusBadge|headOrganizationPoptip).
    |
    */
    'columns_ui' => [
        'id' => [
            'width' => 120,
            'align' => 'center',
        ],
        'shop' => [
            'width' => 150,
        ],
        'manager' => [
            'width' => 160,
        ],
        'curator' => [
            'width' => 160,
        ],
        'name' => [
            'width' => 200,
        ],
        'inn' => [
            'width' => 140,
            'align' => 'right',
        ],
        'head_organization' => [
            'width' => 220,
            'cellType' => 'headOrganizationPoptip',
        ],
        'head_organization_type' => [
            'width' => 100,
            'align' => 'center',
        ],
        'organization_name' => [
            'width' => 220,
        ],
        'status_label' => [
            'width' => 160,
            'align' => 'center',
            'cellType' => 'statusBadge',
        ],
        'approved' => [
            'width' => 120,
            'align' => 'center',
        ],
        'user_id' => [
            'width' => 140,
            'align' => 'center',
        ],
    ],

    'status_options_all_label' => env('SALES_OUTLETS_STATUS_OPTIONS_ALL_LABEL', 'Все статусы'),
];
