<?php

namespace Tests\Unit;

use App\Services\SalesOutlets\Reports\Html\HtmlTableRenderer;
use PHPUnit\Framework\TestCase;

class HtmlTableRendererTest extends TestCase
{
    public function test_it_builds_table_with_headers_and_row_values(): void
    {
        $renderer = new HtmlTableRenderer;

        $html = $renderer->render(
            columns: [
                ['key' => 'id', 'label' => 'ID объекта продаж'],
                ['key' => 'shop', 'label' => 'Магазин'],
            ],
            rows: [
                ['id' => '1', 'shop' => 'Курск'],
            ],
        );

        $this->assertStringContainsString('ID объекта продаж', $html);
        $this->assertStringContainsString('Магазин', $html);
        $this->assertStringContainsString('Курск', $html);
        $this->assertStringContainsString('<table', $html);
    }
}
