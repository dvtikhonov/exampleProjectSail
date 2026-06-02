<?php

namespace Tests\Feature;

use App\Contracts\SalesOutlets\SalesOutletsAsyncJobRepositoryInterface;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Events\ReportJobStatsChanged;
use App\Events\SalesOutletReportJobMutated;
use App\Models\SalesOutletReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesOutletsReportStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SalesOutletReportJob::query()->delete();
    }

    public function test_stats_endpoint_returns_aggregated_counts_by_type_and_status(): void
    {
        $user = User::factory()->create();

        SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Pending,
            'user_id' => $user->id,
            'filters' => ['columns' => ['id']],
        ]);

        SalesOutletReportJob::query()->create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Completed,
            'user_id' => $user->id,
            'filters' => ['columns' => ['id']],
        ]);

        SalesOutletReportJob::query()->create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'report_type' => SalesOutletsReportType::HtmlEmail,
            'status' => AsyncJobStatus::Failed,
            'user_id' => $user->id,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->getJson('/api/sales-outlets/reports/stats')
            ->assertOk()
            ->assertJsonPath('by_type.csv_download.pending', 1)
            ->assertJsonPath('by_type.csv_download.completed', 1)
            ->assertJsonPath('by_type.csv_download.total', 2)
            ->assertJsonPath('by_type.html_email.failed', 1)
            ->assertJsonPath('by_type.html_email.total', 1)
            ->assertJsonStructure([
                'by_type' => [
                    'csv_download' => ['pending', 'processing', 'completed', 'failed', 'total'],
                    'html_email' => ['pending', 'processing', 'completed', 'failed', 'total'],
                ],
                'generated_at',
            ]);
    }

    public function test_create_job_dispatches_report_job_stats_changed_event(): void
    {
        Queue::fake();
        Event::fake([ReportJobStatsChanged::class]);

        $user = User::factory()->create();

        $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->postJson('/api/sales-outlets/reports', [
                'report_type' => SalesOutletsReportType::CsvDownload->value,
                'columns' => ['id'],
            ])
            ->assertAccepted();

        Event::assertDispatched(ReportJobStatsChanged::class, function (ReportJobStatsChanged $event): bool {
            return $event->stats->byType['csv_download']['pending'] === 1
                && $event->stats->byType['csv_download']['total'] === 1;
        });
    }

    public function test_find_by_uuid_does_not_dispatch_mutation_event(): void
    {
        Event::fake([SalesOutletReportJobMutated::class]);

        SalesOutletReportJob::query()->create([
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Pending,
            'filters' => ['columns' => ['id']],
        ]);

        $repository = $this->app->make(SalesOutletsAsyncJobRepositoryInterface::class);
        $repository->findByUuid('55555555-5555-5555-5555-555555555555');

        Event::assertNotDispatched(SalesOutletReportJobMutated::class);
    }

    public function test_update_status_dispatches_report_job_stats_changed_event(): void
    {
        Event::fake([ReportJobStatsChanged::class]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Pending,
            'filters' => [
                'search' => '',
                'status' => '',
                'column_filters' => [],
                'sort' => 'id',
                'direction' => 'asc',
                'columns' => ['id'],
            ],
        ]);

        $repository = $this->app->make(SalesOutletsAsyncJobRepositoryInterface::class);

        $asyncJob = $repository->findByUuid($reportJob->uuid);
        $this->assertNotNull($asyncJob);

        $repository->updateStatus($asyncJob, AsyncJobStatus::Processing);

        Event::assertDispatched(ReportJobStatsChanged::class, function (ReportJobStatsChanged $event): bool {
            return $event->stats->byType['csv_download']['pending'] === 0
                && $event->stats->byType['csv_download']['processing'] === 1
                && $event->stats->byType['csv_download']['total'] === 1;
        });
    }
}
