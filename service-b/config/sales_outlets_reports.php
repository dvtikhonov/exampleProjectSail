<?php

return [
    'storage_disk' => env('SALES_OUTLETS_REPORTS_STORAGE_DISK', env('SALES_OUTLETS_EXPORT_STORAGE_DISK', 'local')),

    'apply_fake_delay_environments' => ['local', 'testing'],

    'types' => [
        'csv_download' => [
            'fake_delay_seconds' => (int) env('SALES_OUTLETS_EXPORT_FAKE_DELAY_SECONDS', 10),
        ],
        'html_email' => [
            'fake_delay_seconds' => (int) env('SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS', 10),
            'recipients' => array_values(array_filter(array_map(
                trim(...),
                explode(',', (string) env('SALES_OUTLETS_MAIL_RECIPIENTS', '')),
            ))),
            'subject' => env('SALES_OUTLETS_MAIL_SUBJECT', 'Объекты продаж — отчёт'),
        ],
    ],
];
