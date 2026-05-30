<?php

namespace App\Services\SalesOutlets\Reports\Strategies;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\SalesOutlets\MailReportConfigProviderInterface;
use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\AbstractSalesOutletsHtmlReportStrategy;
use App\Contracts\SalesOutlets\HtmlTableRendererInterface;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Shared\SalesOutletsDomain\DTO\SalesOutletReportContextDto;

class HtmlEmailReportStrategy extends AbstractSalesOutletsHtmlReportStrategy
{
    public function __construct(
        SalesOutletsDataRepositoryInterface $dataRepository,
        SalesOutletColumnSelector $columnSelector,
        private readonly HtmlTableRendererInterface $htmlTableRenderer,
        private readonly ReportMailSenderInterface $mailSender,
        private readonly MailReportConfigProviderInterface $mailReportConfig,
    ) {
        parent::__construct($dataRepository, $columnSelector);
    }

    public function reportType(): SalesOutletsReportType
    {
        return SalesOutletsReportType::HtmlEmail;
    }

    public function buildHtml(SalesOutletReportContextDto $context): string
    {
        return $this->htmlTableRenderer->render(
            $this->resolveColumns($context),
            $this->resolveRows($context, []),
        );
    }

    public function deliver(SalesOutletAsyncJob $job, string $content): ReportDeliveryResult
    {
        $config = $this->mailReportConfig->config();

        $this->mailSender->send(
            recipients: $config->recipients,
            subject: $config->subject,
            html: $content,
        );

        return ReportDeliveryResult::none();
    }
}
