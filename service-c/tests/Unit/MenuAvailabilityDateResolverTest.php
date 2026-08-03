<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use App\Services\Food\Menu\MenuAvailabilityDateResolver;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Unit-тесты расчёта даты «Блюда на дату» по offsets (мок репозитория).
 */
class MenuAvailabilityDateResolverTest extends TestCase
{
    /** max(offset_days) при нескольких значениях → referenceDate + max. */
    public function test_multiple_offsets_adds_max_days(): void
    {
        // Пт 31.07, offsets [1, 2] → 02.08
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [5 => [1, 2]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-08-02', $result->date);
    }

    /** max(offset_days) > 1 → referenceDate + max(offset_days). */
    public function test_max_offset_greater_than_one_adds_max(): void
    {
        // Пт 31.07, offsets [2, 3] → 03.08
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [5 => [2, 3]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-08-03', $result->date);
    }

    /** max с нулём среди offsets → всё равно +max, не +0. */
    public function test_zero_among_offsets_still_uses_max(): void
    {
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [5 => [0, 2]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-08-02', $result->date);
    }

    /** Единственный offset 0 → referenceDate + 0. */
    public function test_sole_zero_offset_keeps_reference_date(): void
    {
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [5 => [0]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-07-31', $result->date);
    }

    /** Пустой weekday → откат; offset от даты отката, не от «сегодня». */
    public function test_empty_weekday_looks_back_and_offsets_from_rollback_date(): void
    {
        // Пт пусто, Чт [1] → referenceDate=30.07 → 31.07
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [4 => [1]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-07-31', $result->date);
    }

    /** Откат с несколькими offsets: offset от даты отката + max. */
    public function test_lookback_with_multiple_offsets_uses_rollback_date_plus_max(): void
    {
        // Пт пусто, Чт [2, 4] → referenceDate=30.07 → 03.08
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [4 => [2, 4]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-08-03', $result->date);
    }

    /** Пустая таблица offsets → «нет данных». */
    public function test_empty_table_returns_no_data_error(): void
    {
        $repository = $this->createMock(MenuCategoryAvailabilityOffsetRepositoryInterface::class);
        $repository->expects($this->once())->method('hasAnyOffsets')->willReturn(false);
        $repository->expects($this->never())->method('listOffsetDaysForWeekday');

        $resolver = new MenuAvailabilityDateResolver($repository);
        $result = $resolver->resolve(
            CarbonImmutable::parse('2026-07-31', 'Europe/Moscow'),
        );

        $this->assertNull($result->date);
        $this->assertSame('нет данных', $result->error);
    }

    /** Есть записи, но ни один weekday за 7 шагов не дал offsets → «нет данных». */
    public function test_no_matching_weekday_after_lookback_returns_no_data_error(): void
    {
        $result = $this->resolve(
            hasAny: true,
            byWeekday: [],
            now: '2026-07-31',
        );

        $this->assertNull($result->date);
        $this->assertSame('нет данных', $result->error);
    }

    /** resolveForCurrentWeekday: Пт [1,2] → 2026-08-02 без lookback. */
    public function test_resolve_for_current_weekday_friday_offsets_add_max(): void
    {
        $result = $this->resolveForCurrentWeekday(
            byWeekday: [5 => [1, 2]],
            now: '2026-07-31',
        );

        $this->assertNull($result->error);
        $this->assertSame('2026-08-02', $result->date);
    }

    /** resolveForCurrentWeekday: пустой weekday → null, без отката. */
    public function test_resolve_for_current_weekday_empty_returns_null_without_lookback(): void
    {
        $repository = $this->createMock(MenuCategoryAvailabilityOffsetRepositoryInterface::class);
        $repository->expects($this->never())->method('hasAnyOffsets');
        $repository
            ->expects($this->once())
            ->method('listOffsetDaysForWeekday')
            ->with(5)
            ->willReturn([]);

        $resolver = new MenuAvailabilityDateResolver($repository);
        $result = $resolver->resolveForCurrentWeekday(
            CarbonImmutable::parse('2026-07-31', 'Europe/Moscow'),
        );

        $this->assertNull($result->date);
        $this->assertSame('нет данных', $result->error);
    }

    /**
     * @param  array<int, list<int>>  $byWeekday
     */
    private function resolve(bool $hasAny, array $byWeekday, string $now): MenuAvailabilityDateResultDto
    {
        $repository = $this->createStub(MenuCategoryAvailabilityOffsetRepositoryInterface::class);
        $repository->method('hasAnyOffsets')->willReturn($hasAny);
        $repository->method('listOffsetDaysForWeekday')->willReturnCallback(
            static fn (int $weekday): array => $byWeekday[$weekday] ?? [],
        );

        $resolver = new MenuAvailabilityDateResolver($repository);

        return $resolver->resolve(
            CarbonImmutable::parse($now, 'Europe/Moscow'),
        );
    }

    /**
     * @param  array<int, list<int>>  $byWeekday
     */
    private function resolveForCurrentWeekday(array $byWeekday, string $now): MenuAvailabilityDateResultDto
    {
        $repository = $this->createStub(MenuCategoryAvailabilityOffsetRepositoryInterface::class);
        $repository->method('listOffsetDaysForWeekday')->willReturnCallback(
            static fn (int $weekday): array => $byWeekday[$weekday] ?? [],
        );

        $resolver = new MenuAvailabilityDateResolver($repository);

        return $resolver->resolveForCurrentWeekday(
            CarbonImmutable::parse($now, 'Europe/Moscow'),
        );
    }
}
