<?php

namespace Tests\Feature;

use App\Enums\SalesOutletExportStatus;
use App\Jobs\BuildSalesOutletsCsvExportJob;
use App\Models\SalesOutlet;
use App\Models\SalesOutletExportJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;
use Tests\TestCase;

class SalesOutletsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_export_job_with_valid_filters(): void
    {
        Queue::fake();
        $userId = 12345;

        $response = $this
            ->withHeader('X-User-Id', (string) $userId)
            ->postJson('/api/sales-outlets/exports', [
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

        $this->assertDatabaseHas('sales_outlet_export_jobs', [
            'user_id' => $userId,
            'status' => SalesOutletExportStatus::Pending->value,
        ]);

        Queue::assertPushed(BuildSalesOutletsCsvExportJob::class);
    }

    public function test_it_returns_export_status(): void
    {
        $exportJob = SalesOutletExportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'status' => SalesOutletExportStatus::Completed,
            'filters' => ['columns' => ['id']],
            'file_path' => 'exports/file.csv',
        ]);

        $this
            ->getJson('/api/sales-outlets/exports/'.$exportJob->uuid)
            ->assertOk()
            ->assertJsonPath('uuid', $exportJob->uuid)
            ->assertJsonPath('status', SalesOutletExportStatus::Completed->value);
    }

    public function test_download_is_unavailable_until_completed(): void
    {
        $exportJob = SalesOutletExportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'status' => SalesOutletExportStatus::Processing,
            'filters' => ['columns' => ['id']],
        ]);

        $this
            ->getJson('/api/sales-outlets/exports/'.$exportJob->uuid.'/download')
            ->assertConflict()
            ->assertJsonPath('message', 'Export file is not ready.');
    }

    public function test_download_file_name_contains_user_id(): void
    {
        Storage::fake('local');

        $userId = 12345;
        $filePath = 'exports/file.csv';

        Storage::disk('local')->put($filePath, "\xEF\xBB\xBF\"ID\"");

        $exportJob = SalesOutletExportJob::query()->create([
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'user_id' => $userId,
            'status' => SalesOutletExportStatus::Completed,
            'filters' => ['columns' => ['id']],
            'file_path' => $filePath,
        ]);

        $this
            ->get('/api/sales-outlets/exports/'.$exportJob->uuid.'/download')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename=objects-sales-outlets-'.$userId.'.csv');
    }

    public function test_job_builds_completed_csv_file(): void
    {
        Storage::fake('local');
        config(['sales_outlets_export.fake_delay_seconds' => 0]);

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

        $exportJob = SalesOutletExportJob::query()->create([
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

        dispatch_sync(new BuildSalesOutletsCsvExportJob(uuid: $exportJob->uuid));

        $exportJob->refresh();

        $this->assertSame(SalesOutletExportStatus::Completed, $exportJob->status);
        Storage::disk('local')->assertExists($exportJob->file_path);
        $this->assertStringContainsString('"ID объекта продаж";"Магазин"', Storage::disk('local')->get($exportJob->file_path));
        $this->assertStringContainsString('"Курск"', Storage::disk('local')->get($exportJob->file_path));
    }
}
