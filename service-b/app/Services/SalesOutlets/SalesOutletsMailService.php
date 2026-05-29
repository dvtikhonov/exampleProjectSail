<?php

namespace App\Services\SalesOutlets;

use App\Contracts\SalesOutlets\ReportMailSenderInterface;
use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Enums\SalesOutletExportStatus;
use App\Jobs\SendSalesOutletsReportMailJob;
use App\Models\SalesOutletMailJob;
use App\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Repositories\SalesOutlets\SalesOutletsMailRepositoryInterface;
use RuntimeException;
use Throwable;

class SalesOutletsMailService implements SalesOutletsMailApiServiceInterface, SalesOutletsMailWorkerServiceInterface
{
    public function __construct(
        private readonly SalesOutletsMailRepositoryInterface $mailRepository,
        private readonly SalesOutletsDataRepositoryInterface $dataRepository,
        private readonly SalesOutletsExportMetadataRepositoryInterface $metadataRepository,
        private readonly SalesOutletsHtmlReportBuilder $htmlReportBuilder,
        private readonly ReportMailSenderInterface $reportMailSender,
    ) {}

    public function create(SalesOutletExportFilterDto $filters, ?int $userId): SalesOutletMailJob
    {
        $mailJob = $this->mailRepository->create($filters, $userId);

        dispatch(new SendSalesOutletsReportMailJob(uuid: $mailJob->uuid));

        return $mailJob;
    }

    public function findByUuid(string $uuid): ?SalesOutletMailJob
    {
        return $this->mailRepository->findByUuid($uuid);
    }

    public function sendByUuid(string $uuid): void
    {
        $mailJob = $this->mailRepository->findByUuid($uuid);

        if ($mailJob === null) {
            return;
        }

        $this->sendReport($mailJob);
    }

    public function markAsFailed(string $uuid, ?string $errorMessage = null): void
    {
        $mailJob = $this->mailRepository->findByUuid($uuid);

        if ($mailJob === null || $mailJob->status === SalesOutletExportStatus::Failed) {
            return;
        }

        $this->mailRepository->updateStatus(
            $mailJob,
            SalesOutletExportStatus::Failed,
            errorMessage: $errorMessage ?? 'Mail job failed.',
        );
    }

    private function sendReport(SalesOutletMailJob $mailJob): void
    {
        $this->mailRepository->updateStatus($mailJob, SalesOutletExportStatus::Processing);

        try {
            $recipients = config('sales_outlets_mail.recipients', []);

            if ($recipients === []) {
                throw new RuntimeException('Mail recipients are not configured.');
            }

            $delaySeconds = max((int) config('sales_outlets_mail.fake_delay_seconds'), 0);

            if ($delaySeconds > 0) {
                sleep($delaySeconds);
            }

            $filters = SalesOutletExportFilterDto::fromValidated(
                validated: $mailJob->filters,
                allowedColumns: $this->metadataRepository->allowedColumnKeys(),
            );

            $html = $this->htmlReportBuilder->build(
                filters: $filters,
                rows: $this->dataRepository->exportRows($filters),
            );

            $this->reportMailSender->send(
                recipients: $recipients,
                subject: (string) config('sales_outlets_mail.subject'),
                html: $html,
            );

            $this->mailRepository->updateStatus($mailJob, SalesOutletExportStatus::Completed);
        } catch (Throwable $exception) {
            $this->mailRepository->updateStatus(
                $mailJob,
                SalesOutletExportStatus::Failed,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }
    }
}
