<?php

namespace App\Contracts\Max;

interface ReportMaxMessageSenderInterface
{
    public function send(string $text, string $csvContent, string $fileName): void;
}
