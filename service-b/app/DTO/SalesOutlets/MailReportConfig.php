<?php

namespace App\DTO\SalesOutlets;

readonly class MailReportConfig
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function __construct(
        public array $recipients,
        public string $subject,
    ) {}
}
