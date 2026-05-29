<?php

namespace App\Contracts\SalesOutlets;

interface ReportMailSenderInterface
{
    /**
     * @param  array<int, string>  $recipients
     */
    public function send(array $recipients, string $subject, string $html): void;
}
