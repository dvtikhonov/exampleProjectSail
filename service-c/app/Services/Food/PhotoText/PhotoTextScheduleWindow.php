<?php

declare(strict_types=1);

namespace App\Services\Food\PhotoText;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Окно графика PhotoText: ровно {@see self::LENGTH_DAYS} календарных дней
 * (`date_to` = `date_from` + {@see self::END_OFFSET_DAYS}).
 *
 * Источник истины для match/apply и зеркал (SchedulePhoto starter, admin UI).
 */
final class PhotoTextScheduleWindow
{
    /** Число дней в окне включительно. */
    public const int LENGTH_DAYS = 7;

    /** Смещение конца относительно date_from. */
    public const int END_OFFSET_DAYS = self::LENGTH_DAYS - 1;

    /**
     * Конец окна (Y-m-d) по дате начала.
     *
     * @throws InvalidArgumentException некорректный Y-m-d
     */
    public static function dateToFrom(string $dateFrom): string
    {
        $from = DateTimeImmutable::createFromFormat('!Y-m-d', $dateFrom);
        if ($from === false || $from->format('Y-m-d') !== $dateFrom) {
            throw new InvalidArgumentException('Некорректная дата начала графика.');
        }

        return $from->modify('+'.self::END_OFFSET_DAYS.' days')->format('Y-m-d');
    }

    /**
     * Проверяет, что пара дат образует каноническое окно PhotoText.
     */
    public static function matches(string $dateFrom, string $dateTo): bool
    {
        try {
            return self::dateToFrom($dateFrom) === $dateTo;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
