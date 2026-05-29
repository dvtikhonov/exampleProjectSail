<?php

namespace Tests\Unit;

use App\DTO\SalesOutlets\SalesOutletExportFilterDto;
use App\Repositories\SalesOutlets\SalesOutletsExportMetadataRepositoryInterface;
use App\Services\SalesOutlets\SalesOutletColumnSelector;
use App\Services\SalesOutlets\SalesOutletsHtmlReportBuilder;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class SalesOutletsHtmlReportBuilderTest extends TestCase
{
    public function test_it_builds_table_with_headers_and_row_values(): void
    {
        $metadataRepository = $this->createMock(SalesOutletsExportMetadataRepositoryInterface::class);
        $metadataRepository->method('columns')->willReturn([
            ['key' => 'id', 'label' => 'ID объекта продаж'],
            ['key' => 'shop', 'label' => 'Магазин'],
        ]);

        $builder = new SalesOutletsHtmlReportBuilder(new SalesOutletColumnSelector($metadataRepository));

        $filters = SalesOutletExportFilterDto::fromValidated(
            validated: ['columns' => ['id', 'shop']],
            allowedColumns: ['id', 'shop'],
        );

        $html = $builder->build(
            filters: $filters,
            rows: new Collection([
                ['id' => '1', 'shop' => 'Курск'],
            ]),
        );

        $this->assertStringContainsString('ID объекта продаж', $html);
        $this->assertStringContainsString('Магазин', $html);
        $this->assertStringContainsString('Курск', $html);
        $this->assertStringContainsString('<table', $html);
    }
}
