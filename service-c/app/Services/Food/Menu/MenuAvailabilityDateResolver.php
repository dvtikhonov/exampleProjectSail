<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use Carbon\CarbonImmutable;

/**
 * Расчёт даты «Блюда на дату» по offsets категорий меню (Europe/Moscow).
 *
 * Алгоритм:
 * 1. referenceDate = сегодня (MSK), weekday = ISO дня.
 * 2. Собираем все offset_days для этого weekday по всем категориям.
 * 3. Если строк нет — откат referenceDate на −1 день (до 7 шагов).
 * 4. Пустая таблица / нет записей после обхода → ошибка «нет данных».
 * 5. Смещение = max(offset_days) относительно даты отката (referenceDate).
 */
class MenuAvailabilityDateResolver implements MenuAvailabilityDateResolverInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    private const string ERROR_NO_DATA = 'нет данных';

    private const int MAX_WEEKDAY_LOOKBACK = 7;

    public function __construct(
        private readonly MenuCategoryAvailabilityOffsetRepositoryInterface $offsetRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto
    {
        if (! $this->offsetRepository->hasAnyOffsets()) {
            return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
        }

        $referenceDate = ($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->timezone(self::TIMEZONE)
            ->startOfDay();

        for ($step = 0; $step < self::MAX_WEEKDAY_LOOKBACK; $step++) {
            $candidate = $referenceDate->subDays($step);
            $offsetDays = $this->offsetRepository->listOffsetDaysForWeekday($candidate->dayOfWeekIso);

            if ($offsetDays === []) {
                continue;
            }

            $daysToAdd = $this->resolveDaysToAdd($offsetDays);

            return new MenuAvailabilityDateResultDto(
                date: $candidate->addDays($daysToAdd)->format('Y-m-d'),
                error: null,
            );
        }

        return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveForCurrentWeekday(?CarbonImmutable $now = null): MenuAvailabilityDateResultDto
    {
        $referenceDate = ($now ?? CarbonImmutable::now(self::TIMEZONE))
            ->timezone(self::TIMEZONE)
            ->startOfDay();

        $offsetDays = $this->offsetRepository->listOffsetDaysForWeekday($referenceDate->dayOfWeekIso);

        if ($offsetDays === []) {
            return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
        }

        $daysToAdd = $this->resolveDaysToAdd($offsetDays);

        return new MenuAvailabilityDateResultDto(
            date: $referenceDate->addDays($daysToAdd)->format('Y-m-d'),
            error: null,
        );
    }

    /**
     * Агрегированное смещение: максимум offset_days по всем категориям weekday.
     *
     * @param  non-empty-list<int>  $offsetDays
     */
    private function resolveDaysToAdd(array $offsetDays): int
    {
        return max($offsetDays);
    }
}
