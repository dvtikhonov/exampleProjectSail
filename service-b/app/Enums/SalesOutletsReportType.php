<?php

namespace App\Enums;

enum SalesOutletsReportType: string
{
    case CsvDownload = 'csv_download';
    case HtmlEmail = 'html_email';

    public function configKey(): string
    {
        return $this->value;
    }
}
