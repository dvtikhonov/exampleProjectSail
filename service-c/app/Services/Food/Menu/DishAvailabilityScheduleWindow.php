<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Shared\ClockInterface;
use App\Exceptions\Food\FoodDomainException;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Окно дат графика доступности в Europe/Moscow: диапазон, editableFrom, перечисление дней.
 */
class DishAvailabilityScheduleWindow
{
    private const string TIMEZONE = 'Europe/Moscow';

    /** Максимум дней вперёд от сегодня (включительно) в графике. */
    private const int DAYS_FORWARD = 30;

    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Вычисляет диапазон дат сетки доступности.
     *
     * @return array{0: string, 1: string}
     *
     * @throws FoodDomainException
     */
    public function resolveDateRange(?string $dateFrom, ?string $dateTo): array
    {
        $today = $this->moscowToday();
        $editableFrom = $today;
        $maxTo = $this->addDays($today, self::DAYS_FORWARD);

        $from = $dateFrom ?? $editableFrom->format('Y-m-d');
        $to = $dateTo ?? $maxTo->format('Y-m-d');

        if ($from < $editableFrom->format('Y-m-d')) {
            $from = $editableFrom->format('Y-m-d');
        }

        if ($to > $maxTo->format('Y-m-d')) {
            $to = $maxTo->format('Y-m-d');
        }

        if ($from > $to) {
            throw new FoodDomainException('Дата начала диапазона не может быть позже даты окончания.', 422);
        }

        return [$from, $to];
    }

    /**
     * Возвращает первую дату, доступную для редактирования (сегодня по MSK).
     */
    public function editableFrom(): string
    {
        return $this->moscowToday()->format('Y-m-d');
    }

    /**
     * Перечисляет даты в диапазоне включительно.
     *
     * @return list<string>
     */
    public function enumerateDates(string $from, string $to): array
    {
        $dates = [];
        $timezone = new DateTimeZone(self::TIMEZONE);
        $current = new DateTimeImmutable($from, $timezone);
        $end = new DateTimeImmutable($to, $timezone);

        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current = $current->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Сегодняшний календарный день Europe/Moscow.
     */
    private function moscowToday(): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($this->clock->now())
            ->setTimezone(new DateTimeZone(self::TIMEZONE))
            ->setTime(0, 0, 0);
    }

    /**
     * Добавляет календарные дни.
     */
    private function addDays(DateTimeImmutable $date, int $days): DateTimeImmutable
    {
        if ($days === 0) {
            return $date;
        }

        return $date->add(new DateInterval('P'.$days.'D'));
    }
}
