<?php

namespace Tests\Unit;

use App\Services\Max\PlainTextTableRenderer;
use PHPUnit\Framework\TestCase;

class PlainTextTableRendererTest extends TestCase
{
    public function test_it_builds_pipe_separated_rows_with_header(): void
    {
        $renderer = new PlainTextTableRenderer;

        $text = $renderer->render(
            columns: [
                ['key' => 'id', 'label' => 'ID объекта продаж'],
                ['key' => 'shop', 'label' => 'Магазин'],
            ],
            rows: [
                ['id' => '1001', 'shop' => 'Белгород'],
                ['id' => '1002', 'shop' => 'Белгород'],
            ],
        );

        $lines = explode("\n", $text);

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('ID объекта продаж', $lines[0]);
        $this->assertStringContainsString(' | ', $lines[0]);
        $this->assertStringContainsString('1001', $lines[1]);
        $this->assertStringContainsString('Белгород', $lines[1]);
        $this->assertStringContainsString('1002', $lines[2]);
        $this->assertStringNotContainsString('<table', $text);
    }
}
