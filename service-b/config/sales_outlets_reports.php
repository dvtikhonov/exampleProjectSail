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
        'max_message' => [
            'fake_delay_seconds' => (int) env('MAX_MESSAGE_FAKE_DELAY_SECONDS', 10),
            'bot_access_token' => env('MAX_BOT_ACCESS_TOKEN', ''),
            'chat_ids' => array_values(array_filter(array_map(
                static fn (string $id): int => (int) $id,
                array_filter(array_map(
                    trim(...),
                    explode(',', (string) env('MAX_REPORT_CHAT_IDS', '')),
                )),
            ))),
            'user_ids' => array_values(array_filter(array_map(
                static fn (string $id): int => (int) $id,
                array_filter(array_map(
                    trim(...),
                    explode(',', (string) env('MAX_REPORT_USER_IDS', '')),
                )),
            ))),
            'intro' => env('MAX_REPORT_INTRO', 'Объекты продаж — отчёт'),
            'max_text_length' => 4000,
            'api_rate_limit_rps' => 30,
            'rate_limit_retry_max' => 2,
            'rate_limit_retry_delay_ms' => 500,
            'inter_recipient_delay_ms' => 50,
            'attachment_not_ready_retry_max' => 3,
            'attachment_not_ready_retry_delay_ms' => 200,
        ],
    ],
];
