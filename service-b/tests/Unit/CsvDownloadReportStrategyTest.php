<?php

namespace Tests\Unit;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsDataRepositoryInterface;
use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Contracts\SalesOutlets\ReportFileStorageInterface;
use App\Contracts\SalesOutlets\SalesOutletsDownloadableReportStrategyInterface;
use App\Domain\SalesOutlets\SalesOutletAsyncJob;
use App\DTO\SalesOutlets\ReportDeliveryResult;
use App\DTO\SalesOutlets\SalesOutletReportFilterDto;
use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use App\Services\SalesOutlets\Reports\SalesOutletsReportContextFactory;
use App\Services\SalesOutlets\Reports\Strategies\CsvDownloadReportStrategy;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Shared\SalesOutletsDomain\AbstractStrategy\CsvReportWriter;

class CsvDownloadReportStrategyTest extends TestCase
{
    public function test_build_csv_writes_utf8_bom_csv_with_selected_columns(): void
    {

        $strategy = $this->makeStrategy(

            rows: [

                ['id' => '1', 'shop' => 'Курск'],

            ],

        );

        $filters = SalesOutletReportFilterDto::fromValidated(

            validated: ['columns' => ['id', 'shop']],

            allowedColumns: ['id', 'shop'],

        );

        $csv = $strategy->build(

            (new SalesOutletsReportContextFactory)->fromReportFilter($filters),

        );

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $this->assertStringContainsString('"ID объекта продаж";"Магазин"', $csv);

        $this->assertStringContainsString('"1";"Курск"', $csv);

    }

    public function test_deliver_stores_csv_in_report_storage(): void
    {

        $storedPath = null;

        $storedContent = null;

        $fileStorage = $this->createMock(ReportFileStorageInterface::class);

        $fileStorage

            ->expects($this->once())

            ->method('put')

            ->willReturnCallback(function (string $path, string $content) use (&$storedPath, &$storedContent): void {

                $storedPath = $path;

                $storedContent = $content;

            });

        $strategy = $this->makeStrategy(

            rows: [['id' => '42', 'shop' => 'Москва']],

            fileStorage: $fileStorage,

        );

        $job = $this->makeJob();

        $delivery = $strategy->deliver($job, 'csv-content');

        $this->assertSame('reports/sales-outlets-11111111-1111-1111-1111-111111111111.csv', $storedPath);

        $this->assertSame('csv-content', $storedContent);

        $this->assertEquals(

            ReportDeliveryResult::withFile('reports/sales-outlets-11111111-1111-1111-1111-111111111111.csv'),

            $delivery,

        );

    }

    public function test_storage_path_for_job(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame(

            'reports/sales-outlets-11111111-1111-1111-1111-111111111111.csv',

            $strategy->storagePathForJob($this->makeJob()),

        );

    }

    public function test_download_file_name_contains_user_id(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame(

            'objects-sales-outlets-10.csv',

            $strategy->downloadFileName($this->makeJob()),

        );

    }

    public function test_download_file_name_without_user_id(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $job = new SalesOutletAsyncJob(

            uuid: '11111111-1111-1111-1111-111111111111',

            userId: null,

            status: AsyncJobStatus::Processing,

            reportType: SalesOutletsReportType::CsvDownload,

            filters: SalesOutletReportFilterDto::fromValidated(

                validated: ['columns' => ['id', 'shop']],

                allowedColumns: ['id', 'shop'],

            ),

        );

        $this->assertSame('objects-sales-outlets.csv', $strategy->downloadFileName($job));

    }

    public function test_download_content_type_is_csv(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame('text/csv; charset=UTF-8', $strategy->downloadContentType());

    }

    public function test_report_type_is_csv_download(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $this->assertSame(SalesOutletsReportType::CsvDownload, $strategy->reportType());

    }

    public function test_supports_download(): void
    {

        $strategy = $this->makeStrategy(rows: []);

        $this->assertInstanceOf(SalesOutletsDownloadableReportStrategyInterface::class, $strategy);

    }

    /**
     * @param  iterable<int, array<string, int|string|null>>  $rows
     */
    private function makeStrategy(

        iterable $rows,

        ?ReportFileStorageInterface $fileStorage = null,

    ): CsvDownloadReportStrategy {

        $dataRepository = $this->createMock(SalesOutletsDataRepositoryInterface::class);

        $dataRepository

            ->method('reportRows')

            ->willReturn(new Collection($rows));

        $metadataRepository = $this->createMock(SalesOutletsMetadataRepositoryInterface::class);

        $metadataRepository->method('columns')->willReturn([

            ['key' => 'id', 'label' => 'ID объекта продаж'],

            ['key' => 'shop', 'label' => 'Магазин'],

        ]);

        return new CsvDownloadReportStrategy(

            dataRepository: $dataRepository,

            columnSelector: new SalesOutletColumnSelector($metadataRepository),

            csvWriter: new CsvReportWriter,

            fileStorage: $fileStorage ?? $this->createMock(ReportFileStorageInterface::class),

        );

    }

    private function makeJob(): SalesOutletAsyncJob
    {

        return new SalesOutletAsyncJob(

            uuid: '11111111-1111-1111-1111-111111111111',

            userId: 10,

            status: AsyncJobStatus::Processing,

            reportType: SalesOutletsReportType::CsvDownload,

            filters: SalesOutletReportFilterDto::fromValidated(

                validated: ['columns' => ['id', 'shop']],

                allowedColumns: ['id', 'shop'],

            ),

        );

    }
}
