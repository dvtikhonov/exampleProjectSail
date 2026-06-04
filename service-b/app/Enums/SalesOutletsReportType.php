<?php

namespace App\Enums;

enum SalesOutletsReportType: string
{
    case CsvDownload = 'csv_download';
    case HtmlEmail = 'html_email';
    case MaxMessage = 'max_message';

    public function configKey(): string
    {
        return $this->value;
    }
}
