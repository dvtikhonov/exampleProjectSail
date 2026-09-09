<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\MenuAvailabilityDateResolverInterface;
use App\Contracts\Food\Menu\MenuCategoryAvailabilityOffsetRepositoryInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Food\Menu\MenuAvailabilityDateResultDto;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

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
        private readonly ClockInterface $clock,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function resolve(?DateTimeInterface $now = null): MenuAvailabilityDateResultDto
    {
        if (! $this->offsetRepository->hasAnyOffsets()) {
            return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
        }

        $referenceDate = $this->toMoscowStartOfDay($now);

        for ($step = 0; $step < self::MAX_WEEKDAY_LOOKBACK; $step++) {
            $candidate = $this->subDays($referenceDate, $step);
            $offsetDays = $this->offsetRepository->listOffsetDaysForWeekday($this->isoWeekday($candidate));

            if ($offsetDays === []) {
                continue;
            }

            $daysToAdd = $this->resolveDaysToAdd($offsetDays);

            return new MenuAvailabilityDateResultDto(
                date: $this->addDays($candidate, $daysToAdd)->format('Y-m-d'),
                error: null,
            );
        }

        return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
    }

    /**
     * {@inheritDoc}
     */
    public function resolveForCurrentWeekday(?DateTimeInterface $now = null): MenuAvailabilityDateResultDto
    {
        $referenceDate = $this->toMoscowStartOfDay($now);

        $offsetDays = $this->offsetRepository->listOffsetDaysForWeekday($this->isoWeekday($referenceDate));

        if ($offsetDays === []) {
            return new MenuAvailabilityDateResultDto(date: null, error: self::ERROR_NO_DATA);
        }

        $daysToAdd = $this->resolveDaysToAdd($offsetDays);

        return new MenuAvailabilityDateResultDto(
            date: $this->addDays($referenceDate, $daysToAdd)->format('Y-m-d'),
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

    /**
     * Нормализует «сейчас» к началу календарного дня Europe/Moscow.
     */
    private function toMoscowStartOfDay(?DateTimeInterface $now): DateTimeImmutable
    {
        $instant = DateTimeImmutable::createFromInterface($now ?? $this->clock->now());

        return $instant
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0);
    }

    /**
     * ISO-номер дня недели (1=пн … 7=вс).
     */
    private function isoWeekday(DateTimeImmutable $date): int
    {
        return (int) $date->format('N');
    }

    /**
     * Вычитает календарные дни.
     */
    private function subDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $date;
        }

        return $date->sub(new DateInterval('P'.$days.'D'));
    }

    /**
     * Добавляет календарные дни.
     */
    private function addDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $date;
        }

        if ($days < 0) {
            return $this->subDays($date, abs($days));
        }

        return $date->add(new DateInterval('P'.$days.'D'));
    }
}
