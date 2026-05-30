<?php

namespace Tests\Feature;

use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Jobs\BuildSalesOutletsReportJob;
use App\Mail\SalesOutletsReportMailable;
use App\Models\SalesOutlet;
use App\Models\SalesOutletReportJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Tests\TestCase;

class SalesOutletsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_csv_download_report_job_with_valid_filters(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->postJson('/api/sales-outlets/reports', [
                'report_type' => SalesOutletsReportType::CsvDownload->value,
                'search' => 'Курск',
                'status' => 'approved',
                'column_filters' => ['shop' => 'Курск'],
                'sort' => 'shop',
                'direction' => 'desc',
                'columns' => ['id', 'shop'],
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('status', AsyncJobStatus::Pending->value)
            ->assertJsonPath('error_message', null);

        $this->assertDatabaseHas('sales_outlet_report_jobs', [
            'uuid' => $response->json('uuid'),
            'user_id' => $user->id,
            'report_type' => SalesOutletsReportType::CsvDownload->value,
            'status' => AsyncJobStatus::Pending->value,
        ]);

        Queue::assertPushed(BuildSalesOutletsReportJob::class);
    }

    public function test_it_creates_html_email_report_job_with_valid_filters(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->postJson('/api/sales-outlets/reports', [
                'report_type' => SalesOutletsReportType::HtmlEmail->value,
                'search' => 'Курск',
                'status' => 'approved',
                'column_filters' => ['shop' => 'Курск'],
                'sort' => 'shop',
                'direction' => 'desc',
                'columns' => ['id', 'shop'],
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('status', AsyncJobStatus::Pending->value)
            ->assertJsonPath('error_message', null);

        $this->assertDatabaseHas('sales_outlet_report_jobs', [
            'uuid' => $response->json('uuid'),
            'user_id' => $user->id,
            'report_type' => SalesOutletsReportType::HtmlEmail->value,
            'status' => AsyncJobStatus::Pending->value,
        ]);

        Queue::assertPushed(BuildSalesOutletsReportJob::class);
    }

    public function test_it_returns_report_status(): void
    {
        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Completed,
            'filters' => ['columns' => ['id']],
            'file_path' => 'reports/file.csv',
        ]);

        $this
            ->getJson('/api/sales-outlets/reports/'.$reportJob->uuid)
            ->assertOk()
            ->assertJsonPath('uuid', $reportJob->uuid)
            ->assertJsonPath('status', AsyncJobStatus::Completed->value);
    }

    public function test_download_is_unavailable_until_completed(): void
    {
        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Processing,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->getJson('/api/sales-outlets/reports/'.$reportJob->uuid.'/download')
            ->assertConflict()
            ->assertJsonPath('message', 'Report file is not ready.');
    }

    public function test_download_file_name_contains_user_id(): void
    {
        Storage::fake('local');

        $userId = 12345;
        $filePath = 'reports/file.csv';

        Storage::disk('local')->put($filePath, "\xEF\xBB\xBF\"ID\"");

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $userId,
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Completed,
            'filters' => ['columns' => ['id']],
            'file_path' => $filePath,
        ]);

        $this
            ->get('/api/sales-outlets/reports/'.$reportJob->uuid.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename=objects-sales-outlets-'.$userId.'.csv');
    }

    public function test_csv_download_job_builds_completed_file(): void
    {
        Storage::fake('local');
        config(['sales_outlets_reports.types.csv_download.fake_delay_seconds' => 0]);

        SalesOutlet::query()->create([
            'shop' => 'Курск',
            'manager' => 'Семенов И. П.',
            'curator' => 'Лебедева А. Н.',
            'name' => 'Центральный',
            'inn' => '4632014589',
            'head_organization' => 'ООО Центральная сеть',
            'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
            'organization_name' => 'ООО Центральная сеть',
            'status' => SalesOutletStatus::Approved,
            'approved' => 'Да',
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Pending,
            'filters' => [
                'search' => '',
                'status' => 'approved',
                'column_filters' => [],
                'sort' => 'id',
                'direction' => 'asc',
                'columns' => ['id', 'shop'],
            ],
        ]);

        dispatch_sync(new BuildSalesOutletsReportJob(uuid: $reportJob->uuid));

        $reportJob->refresh();

        $this->assertSame(AsyncJobStatus::Completed, $reportJob->status);
        $this->assertStringStartsWith('reports/', $reportJob->file_path);
        Storage::disk('local')->assertExists($reportJob->file_path);
        $this->assertStringContainsString('"ID объекта продаж";"Магазин"', Storage::disk('local')->get($reportJob->file_path));
        $this->assertStringContainsString('"Курск"', Storage::disk('local')->get($reportJob->file_path));
    }

    public function test_html_email_job_fails_when_recipients_are_empty(): void
    {
        Mail::fake();
        config([
            'sales_outlets_reports.types.html_email.recipients' => [],
            'sales_outlets_reports.types.html_email.fake_delay_seconds' => 0,
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'report_type' => SalesOutletsReportType::HtmlEmail,
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

        try {
            dispatch_sync(new BuildSalesOutletsReportJob(uuid: $reportJob->uuid));
        } catch (\Throwable) {
            //
        }

        $reportJob->refresh();

        $this->assertSame(AsyncJobStatus::Failed, $reportJob->status);
        $this->assertSame('Mail recipients are not configured.', $reportJob->error_message);
        Mail::assertNothingSent();
    }

    public function test_html_email_job_builds_html_and_sends_mail(): void
    {
        Mail::fake();
        config([
            'sales_outlets_reports.types.html_email.recipients' => ['reports@example.test'],
            'sales_outlets_reports.types.html_email.subject' => 'Объекты продаж — отчёт',
            'sales_outlets_reports.types.html_email.fake_delay_seconds' => 0,
        ]);

        SalesOutlet::query()->create([
            'shop' => 'Курск',
            'manager' => 'Семенов И. П.',
            'curator' => 'Лебедева А. Н.',
            'name' => 'Центральный',
            'inn' => '4632014589',
            'head_organization' => 'ООО Центральная сеть',
            'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
            'organization_name' => 'ООО Центральная сеть',
            'status' => SalesOutletStatus::Approved,
            'approved' => 'Да',
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'report_type' => SalesOutletsReportType::HtmlEmail,
            'status' => AsyncJobStatus::Pending,
            'filters' => [
                'search' => '',
                'status' => 'approved',
                'column_filters' => [],
                'sort' => 'id',
                'direction' => 'asc',
                'columns' => ['id', 'shop'],
            ],
        ]);

        dispatch_sync(new BuildSalesOutletsReportJob(uuid: $reportJob->uuid));

        $reportJob->refresh();

        $this->assertSame(AsyncJobStatus::Completed, $reportJob->status);
        Mail::assertSent(SalesOutletsReportMailable::class, function (SalesOutletsReportMailable $mail): bool {
            return $mail->hasTo('reports@example.test');
        });
    }

    public function test_download_returns_not_found_for_html_email_report(): void
    {
        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'report_type' => SalesOutletsReportType::HtmlEmail,
            'status' => AsyncJobStatus::Completed,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->getJson('/api/sales-outlets/reports/'.$reportJob->uuid.'/download')
            ->assertNotFound();
    }

    public function test_it_rejects_invalid_report_type(): void
    {
        $user = User::factory()->create();

        $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->postJson('/api/sales-outlets/reports', [
                'report_type' => 'pdf_export',
                'columns' => ['id'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['report_type']);
    }
}
