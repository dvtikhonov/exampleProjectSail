<?php

namespace App\Contracts\SalesOutlets;

use App\DTO\SalesOutlets\MailReportConfig;

interface MailReportConfigProviderInterface
{
    public function config(): MailReportConfig;
}
