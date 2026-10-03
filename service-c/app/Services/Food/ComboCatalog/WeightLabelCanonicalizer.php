<?php

declare(strict_types=1);

namespace App\Services\Food\ComboCatalog;

use App\Contracts\Food\ComboCatalog\WeightLabelCanonicalizerInterface;
use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Канонизация подписи веса: «120г» / «120 г.» → «120 грамм».
 */
final class WeightLabelCanonicalizer implements WeightLabelCanonicalizerInterface
{
    /**
     * {@inheritDoc}
     */
    public function fromDish(DishRecord $dish): ?string
    {
        if (trim($dish->weight) === '') {
            return null;
        }

        return $this->fromAmountAndUnit($dish->weight, $dish->weightUnit);
    }

    /**
     * {@inheritDoc}
     */
    public function fromAmountAndUnit(string|int|float $amount, DishWeightUnit $unit): string
    {
        return $this->formatCanonical($this->normalizeAmount($amount), $unit);
    }

    /**
     * {@inheritDoc}
     */
    public function fromLabel(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (! preg_match(
            '/^(\d+(?:[.,]\d+)?)\s*([a-zA-Zа-яА-ЯёЁ.]+)\.?$/u',
            $trimmed,
            $matches,
        )) {
            return null;
        }

        $unit = DishWeightUnit::tryFromAlias($matches[2]);
        if ($unit === null) {
            return null;
        }

        $amount = str_replace(',', '.', $matches[1]);

        return $this->fromAmountAndUnit($amount, $unit);
    }

    private function normalizeAmount(string|int|float $amount): string
    {
        if (is_int($amount)) {
            return (string) $amount;
        }

        if (is_float($amount)) {
            return (string) (int) round($amount);
        }

        $normalized = str_replace(',', '.', trim($amount));
        if ($normalized === '' || ! is_numeric($normalized)) {
            return '0';
        }

        return (string) (int) round((float) $normalized);
    }

    private function formatCanonical(string $amount, DishWeightUnit $unit): string
    {
        return $amount.' '.$unit->canonicalWord();
    }
}
