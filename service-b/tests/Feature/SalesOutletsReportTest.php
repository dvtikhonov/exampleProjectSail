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
use Illuminate\Support\Facades\Http;
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
        $user = User::factory()->create();

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $user->id,
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Completed,
            'filters' => ['columns' => ['id']],
            'file_path' => 'reports/file.csv',
        ]);

        $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->getJson('/api/sales-outlets/reports/'.$reportJob->uuid)
            ->assertOk()
            ->assertJsonPath('uuid', $reportJob->uuid)
            ->assertJsonPath('status', AsyncJobStatus::Completed->value);
    }

    public function test_download_is_unavailable_until_completed(): void
    {
        $user = User::factory()->create();

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $user->id,
            'report_type' => SalesOutletsReportType::CsvDownload,
            'status' => AsyncJobStatus::Processing,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->getJson('/api/sales-outlets/reports/'.$reportJob->uuid.'/download')
            ->assertConflict()
            ->assertJsonPath('message', 'Report file is not ready.');
    }

    public function test_download_file_name_contains_user_id(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $userId = $user->id;
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
            ->withHeader('X-User-Id', (string) $userId)
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
        $user = User::factory()->create();

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'user_id' => $user->id,
            'report_type' => SalesOutletsReportType::HtmlEmail,
            'status' => AsyncJobStatus::Completed,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->withHeader('X-User-Id', (string) $user->id)
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

    public function test_it_creates_max_message_report_job_with_valid_filters(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $response = $this
            ->withHeader('X-User-Id', (string) $user->id)
            ->postJson('/api/sales-outlets/reports', [
                'report_type' => SalesOutletsReportType::MaxMessage->value,
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
            'report_type' => SalesOutletsReportType::MaxMessage->value,
            'status' => AsyncJobStatus::Pending->value,
        ]);

        Queue::assertPushed(BuildSalesOutletsReportJob::class);
    }

    public function test_max_message_job_fails_when_recipients_are_empty(): void
    {
        Http::fake();
        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => 'test-token',
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '77777777-7777-7777-7777-777777777777',
            'report_type' => SalesOutletsReportType::MaxMessage,
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
        $this->assertSame('MAX report recipients are not configured.', $reportJob->error_message);
        Http::assertNothingSent();
    }

    public function test_max_message_job_sends_intro_text_and_csv_attachment_via_max_api(): void
    {
        $this->fakeMaxApiWithCsvUpload();

        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [12345],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => 'test-max-token',
            'sales_outlets_reports.types.max_message.intro' => 'Объекты продаж — отчёт',
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
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
            'uuid' => '88888888-8888-8888-8888-888888888888',
            'report_type' => SalesOutletsReportType::MaxMessage,
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
        Http::assertSentCount(3);

        Http::assertSent(function ($request): bool {
            $attachments = $request['attachments'] ?? [];

            return str_contains($request->url(), 'chat_id=12345')
                && ! str_contains($request->url(), 'test-max-token')
                && $request->hasHeader('Authorization', 'test-max-token')
                && $request['text'] === 'Объекты продаж — отчёт'
                && ! str_contains((string) $request['text'], 'Курск')
                && ($attachments[0]['type'] ?? null) === 'file'
                && ($attachments[0]['payload']['token'] ?? null) === 'csv-file-token';
        });

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'fu.test/upload')
                && str_contains((string) $request->body(), 'Курск');
        });
    }

    public function test_max_message_job_fails_on_401_without_leaking_token(): void
    {
        Http::fake([
            'platform-api.max.ru/*' => Http::response(['error' => 'unauthorized'], 401),
        ]);

        $token = 'revoked-secret-max-token-xyz';

        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [999],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => $token,
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => '99999999-9999-9999-9999-999999999999',
            'report_type' => SalesOutletsReportType::MaxMessage,
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
        $this->assertStringContainsString('MAX_BOT_ACCESS_TOKEN', (string) $reportJob->error_message);
        $this->assertStringNotContainsString($token, (string) $reportJob->error_message);
        Http::assertSent(fn ($request): bool => ! str_contains($request->url(), $token));
    }

    public function test_max_message_job_fails_on_429_without_leaking_token(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/uploads')) {
                return Http::response(['url' => 'https://fu.test/upload'], 200);
            }

            if (str_contains($request->url(), 'fu.test/upload')) {
                return Http::response(['token' => 'csv-file-token'], 200);
            }

            return Http::response(['error' => 'rate limit'], 429);
        });

        $token = 'rate-limit-secret-token';

        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [777],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => $token,
            'sales_outlets_reports.types.max_message.rate_limit_retry_max' => 2,
            'sales_outlets_reports.types.max_message.rate_limit_retry_delay_ms' => 0,
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'report_type' => SalesOutletsReportType::MaxMessage,
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
        $this->assertStringContainsString('ограничил частоту', (string) $reportJob->error_message);
        $this->assertStringNotContainsString($token, (string) $reportJob->error_message);
        Http::assertSentCount(5);
    }

    public function test_max_message_job_fails_on_503_without_leaking_token(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/uploads')) {
                return Http::response(['url' => 'https://fu.test/upload'], 200);
            }

            if (str_contains($request->url(), 'fu.test/upload')) {
                return Http::response(['token' => 'csv-file-token'], 200);
            }

            return Http::response(['error' => 'unavailable'], 503);
        });

        $token = 'unavailable-secret-token';

        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [888],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => $token,
            'sales_outlets_reports.types.max_message.rate_limit_retry_max' => 2,
            'sales_outlets_reports.types.max_message.rate_limit_retry_delay_ms' => 0,
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
        ]);

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'report_type' => SalesOutletsReportType::MaxMessage,
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
        $this->assertStringContainsString('временно недоступен', (string) $reportJob->error_message);
        $this->assertStringNotContainsString($token, (string) $reportJob->error_message);
        Http::assertSentCount(5);
    }

    public function test_max_message_job_sends_full_csv_attachment_for_large_dataset(): void
    {
        $this->fakeMaxApiWithCsvUpload();

        config([
            'sales_outlets_reports.types.max_message.chat_ids' => [555],
            'sales_outlets_reports.types.max_message.user_ids' => [],
            'sales_outlets_reports.types.max_message.bot_access_token' => 'truncate-test-token',
            'sales_outlets_reports.types.max_message.intro' => 'Объекты продаж — отчёт',
            'sales_outlets_reports.types.max_message.fake_delay_seconds' => 0,
        ]);

        for ($i = 1; $i <= 80; $i++) {
            SalesOutlet::query()->create([
                'shop' => "Магазин {$i} — ".str_repeat('данные ', 20),
                'manager' => "Менеджер {$i}",
                'curator' => "Куратор {$i}",
                'name' => "Точка {$i}",
                'inn' => sprintf('46320145%02d', $i % 100),
                'head_organization' => 'ООО Тестовая сеть',
                'head_organization_type' => HeadOrganizationType::LimitedLiabilityCompany,
                'organization_name' => 'ООО Тестовая сеть',
                'status' => SalesOutletStatus::Approved,
                'approved' => 'Да',
            ]);
        }

        $reportJob = SalesOutletReportJob::query()->create([
            'uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'report_type' => SalesOutletsReportType::MaxMessage,
            'status' => AsyncJobStatus::Pending,
            'filters' => [
                'search' => '',
                'status' => 'approved',
                'column_filters' => [],
                'sort' => 'id',
                'direction' => 'asc',
                'columns' => ['id', 'shop', 'manager', 'curator', 'name'],
            ],
        ]);

        dispatch_sync(new BuildSalesOutletsReportJob(uuid: $reportJob->uuid));

        $reportJob->refresh();

        $this->assertSame(AsyncJobStatus::Completed, $reportJob->status);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'chat_id=555')
                && ($request['text'] ?? '') === 'Объекты продаж — отчёт'
                && ! str_contains((string) ($request['text'] ?? ''), 'Показаны первые');
        });

        Http::assertSent(function ($request): bool {
            $body = (string) $request->body();

            return str_contains($request->url(), 'fu.test/upload')
                && str_contains($body, 'Магазин 1')
                && str_contains($body, 'Магазин 80');
        });
    }

    private function fakeMaxApiWithCsvUpload(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'platform-api.max.ru/uploads')) {
                return Http::response(['url' => 'https://fu.test/upload'], 200);
            }

            if (str_contains($request->url(), 'fu.test/upload')) {
                return Http::response(['token' => 'csv-file-token'], 200);
            }

            if (str_contains($request->url(), 'platform-api.max.ru/messages')) {
                return Http::response(['message' => ['id' => 1]], 200);
            }

            return Http::response(null, 404);
        });
    }
}
