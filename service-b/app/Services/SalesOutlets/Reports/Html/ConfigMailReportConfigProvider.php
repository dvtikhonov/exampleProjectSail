<?php

namespace App\Services\SalesOutlets\Reports\Html;

use App\Contracts\SalesOutlets\MailReportConfigProviderInterface;
use App\DTO\SalesOutlets\MailReportConfig;
use Illuminate\Contracts\Config\Repository;
use RuntimeException;

class ConfigMailReportConfigProvider implements MailReportConfigProviderInterface
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function config(): MailReportConfig
    {
        $recipients = array_values((array) $this->config->get(
            'sales_outlets_reports.types.html_email.recipients',
            [],
        ));

        if ($recipients === []) {
            throw new RuntimeException('Mail recipients are not configured.');
        }

        return new MailReportConfig(
            recipients: $recipients,
            subject: (string) $this->config->get(
                'sales_outlets_reports.types.html_email.subject',
                '',
            ),
        );
    }
}
