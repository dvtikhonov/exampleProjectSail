<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Food\PhotoText\PhotoTextScheduleWindow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PhotoTextScheduleWindowTest extends TestCase
{
    /** date_to = date_from + END_OFFSET_DAYS, длина окна LENGTH_DAYS. */
    public function test_date_to_from_adds_end_offset_days(): void
    {
        $this->assertSame(7, PhotoTextScheduleWindow::LENGTH_DAYS);
        $this->assertSame(6, PhotoTextScheduleWindow::END_OFFSET_DAYS);
        $this->assertSame('2026-10-24', PhotoTextScheduleWindow::dateToFrom('2026-10-18'));
    }

    /** Переход месяца и високосный февраль. */
    public function test_date_to_from_crosses_month_and_leap_day(): void
    {
        $this->assertSame('2026-03-05', PhotoTextScheduleWindow::dateToFrom('2026-02-27'));
        $this->assertSame('2024-03-04', PhotoTextScheduleWindow::dateToFrom('2024-02-27'));
    }

    /** Невалидный Y-m-d → InvalidArgumentException. */
    public function test_date_to_from_rejects_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PhotoTextScheduleWindow::dateToFrom('2026-02-31');
    }

    /** matches — каноническая пара true, сдвиг false. */
    public function test_matches_canonical_range(): void
    {
        $this->assertTrue(PhotoTextScheduleWindow::matches('2026-10-18', '2026-10-24'));
        $this->assertFalse(PhotoTextScheduleWindow::matches('2026-10-18', '2026-10-25'));
        $this->assertFalse(PhotoTextScheduleWindow::matches('not-a-date', '2026-10-24'));
    }
}
