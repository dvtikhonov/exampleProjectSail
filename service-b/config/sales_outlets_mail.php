<?php

return [
    'recipients' => array_values(array_filter(array_map(
        trim(...),
        explode(',', (string) env('SALES_OUTLETS_MAIL_RECIPIENTS', '')),
    ))),
    'subject' => env('SALES_OUTLETS_MAIL_SUBJECT', 'Объекты продаж — отчёт'),
    'fake_delay_seconds' => (int) env('SALES_OUTLETS_MAIL_FAKE_DELAY_SECONDS', 10),
];
