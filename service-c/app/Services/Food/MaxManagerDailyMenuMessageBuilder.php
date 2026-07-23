<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\MaxManagerDailyMenuMessageBuilderInterface;
use App\DTO\Food\DailyMenuDishPartDto;
use App\DTO\Food\DailyMenuLineDto;
use App\DTO\Food\MaxManagerDailyMenuMessagesDto;
use App\Enums\Food\DailyMenuLineType;
use Carbon\CarbonImmutable;

/**
 * Тексты ежедневного меню для пересылки менеджерами MAX.
 */
class MaxManagerDailyMenuMessageBuilder implements MaxManagerDailyMenuMessageBuilderInterface
{
    private const string TIMEZONE = 'Europe/Moscow';

    private const string ORDER_RULES_FOOTER = 'С ПН по ЧТ принимаем заказы до 10.00 и привозим еду на следующий день утром. Заказы, принятые после 10 часов, будут обслуживаться по факту наличия готовой продукции. В пятницу принимаем заказы на понедельник.';

    private const string DELIVERY_FOOTER = 'Стоимость доставки 100 руб., при заказе на 1 тысячу рублей и больше – доставка бесплатно.';

    /**
     * {@inheritDoc}
     */
    public function build(CarbonImmutable $menuDate, array $lines): MaxManagerDailyMenuMessagesDto
    {
        $body = $this->buildBody($menuDate, $lines);

        return new MaxManagerDailyMenuMessagesDto(
            withoutDelivery: $body."\n".self::ORDER_RULES_FOOTER,
            withDelivery: $body."\n".self::ORDER_RULES_FOOTER.' '.self::DELIVERY_FOOTER,
        );
    }

    /**
     * @param  list<DailyMenuLineDto>  $lines
     */
    private function buildBody(CarbonImmutable $menuDate, array $lines): string
    {
        $dateLabel = $menuDate->timezone(self::TIMEZONE)->format('d.m.y');
        $parts = [
            'Добрый день!',
            sprintf('Меню на %s:', $dateLabel),
        ];

        $number = 1;

        foreach ($lines as $line) {
            $formatted = $this->formatLine($number, $line);

            if ($formatted === null) {
                continue;
            }

            $parts[] = $formatted;
            $number++;
        }

        return implode("\n", $parts);
    }

    private function formatLine(int $number, DailyMenuLineDto $line): ?string
    {
        return match ($line->type) {
            DailyMenuLineType::Single => $this->formatSingleLine($number, $line),
            DailyMenuLineType::Combo => $this->formatComboLine($number, $line),
        };
    }

    private function formatSingleLine(int $number, DailyMenuLineDto $line): ?string
    {
        $part = $line->parts[0] ?? null;

        if (! $part instanceof DailyMenuDishPartDto || trim($part->name) === '') {
            return null;
        }

        $text = sprintf('%d. %s', $number, $part->name);

        if ($part->description !== null && $part->description !== '') {
            $text .= sprintf(' (%s)', $part->description);
        }

        if ($part->weightLabel !== null && $part->weightLabel !== '') {
            $text .= ', '.$part->weightLabel;
        }

        $text .= sprintf(' – %sр', $this->formatRubles($part->price));

        return $text;
    }

    private function formatComboLine(int $number, DailyMenuLineDto $line): ?string
    {
        if (count($line->parts) < 2) {
            return null;
        }

        $names = [];
        $weights = [];
        $priceSum = 0.0;

        foreach ($line->parts as $part) {
            $name = trim($part->name);

            if ($name === '') {
                continue;
            }

            $names[] = $name;
            $weights[] = $part->weightLabel ?? '';
            $priceSum += $part->price;
        }

        if (count($names) < 2) {
            return null;
        }

        $text = sprintf('%d. %s', $number, implode(' / ', $names));

        if ($this->hasAnyWeight($weights)) {
            $text .= ', '.implode(' / ', $weights);
        }

        $text .= sprintf(' – %sр', $this->formatRubles($priceSum));

        return $text;
    }

    /**
     * @param  list<string>  $weights
     */
    private function hasAnyWeight(array $weights): bool
    {
        foreach ($weights as $weight) {
            if ($weight !== '') {
                return true;
            }
        }

        return false;
    }

    private function formatRubles(float $amount): string
    {
        return (string) (int) round($amount);
    }
}
