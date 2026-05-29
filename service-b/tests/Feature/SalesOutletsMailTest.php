<?php

namespace Tests\Feature;

use App\Enums\SalesOutletExportStatus;
use App\Jobs\SendSalesOutletsReportMailJob;
use App\Mail\SalesOutletsReportMailable;
use App\Models\SalesOutlet;
use App\Models\SalesOutletMailJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Tests\TestCase;

class SalesOutletsMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_mail_job_with_valid_filters(): void
    {
        Queue::fake();
        $userId = 12345;

        $response = $this
            ->withHeader('X-User-Id', (string) $userId)
            ->postJson('/api/sales-outlets/mail', [
                'search' => 'Курск',
                'status' => 'approved',
                'column_filters' => ['shop' => 'Курск'],
                'sort' => 'shop',
                'direction' => 'desc',
                'columns' => ['id', 'shop'],
            ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('status', SalesOutletExportStatus::Pending->value)
            ->assertJsonPath('error_message', null);

        $this->assertDatabaseHas('sales_outlet_mail_jobs', [
            'user_id' => $userId,
            'status' => SalesOutletExportStatus::Pending->value,
        ]);

        Queue::assertPushed(SendSalesOutletsReportMailJob::class);
    }

    public function test_it_returns_mail_job_status(): void
    {
        $mailJob = SalesOutletMailJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'status' => SalesOutletExportStatus::Completed,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->getJson('/api/sales-outlets/mail/'.$mailJob->uuid)
            ->assertOk()
            ->assertJsonPath('uuid', $mailJob->uuid)
            ->assertJsonPath('status', SalesOutletExportStatus::Completed->value);
    }

    public function test_job_fails_when_recipients_are_empty(): void
    {
        Mail::fake();
        config([
            'sales_outlets_mail.recipients' => [],
            'sales_outlets_mail.fake_delay_seconds' => 0,
        ]);

        $mailJob = SalesOutletMailJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'status' => SalesOutletExportStatus::Pending,
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
            dispatch_sync(new SendSalesOutletsReportMailJob(uuid: $mailJob->uuid));
        } catch (\Throwable) {
            //
        }

        $mailJob->refresh();

        $this->assertSame(SalesOutletExportStatus::Failed, $mailJob->status);
        $this->assertSame('Mail recipients are not configured.', $mailJob->error_message);
        Mail::assertNothingSent();
    }

    public function test_job_builds_html_and_sends_mail(): void
    {
        Mail::fake();
        config([
            'sales_outlets_mail.recipients' => ['reports@example.test'],
            'sales_outlets_mail.subject' => 'Объекты продаж — отчёт',
            'sales_outlets_mail.fake_delay_seconds' => 0,
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

        $mailJob = SalesOutletMailJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'status' => SalesOutletExportStatus::Pending,
            'filters' => [
                'search' => '',
                'status' => 'approved',
                'column_filters' => [],
                'sort' => 'id',
                'direction' => 'asc',
                'columns' => ['id', 'shop'],
            ],
        ]);

        dispatch_sync(new SendSalesOutletsReportMailJob(uuid: $mailJob->uuid));

        $mailJob->refresh();

        $this->assertSame(SalesOutletExportStatus::Completed, $mailJob->status);
        Mail::assertSent(SalesOutletsReportMailable::class, function (SalesOutletsReportMailable $mail): bool {
            return $mail->hasTo('reports@example.test');
        });
    }
}
