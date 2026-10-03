<?php

declare(strict_types=1);

namespace App\Services\Food\BrisklySync;

/**
 * Нормализация и сравнение decimal-цен Briskly sync.
 */
final class BrisklySyncPrice
{
    /**
     * Нормализует цену к строке с двумя знаками.
     */
    public static function normalize(mixed $price): string
    {
        if (is_int($price) || is_float($price)) {
            return number_format((float) $price, 2, '.', '');
        }

        $raw = str_replace(',', '.', trim((string) $price));
        if ($raw === '' || ! is_numeric($raw)) {
            return '0.00';
        }

        return number_format((float) $raw, 2, '.', '');
    }

    /**
     * Сравнивает две цены после нормализации.
     */
    public static function equal(mixed $a, mixed $b): bool
    {
        return self::normalize($a) === self::normalize($b);
    }

    /**
     * Относительная |Δ| относительно текущей цены Briskly.
     */
    public static function relativeDelta(mixed $sourcePrice, mixed $brisklyPrice): float
    {
        $source = (float) self::normalize($sourcePrice);
        $briskly = (float) self::normalize($brisklyPrice);

        if ($briskly == 0.0) {
            return $source == 0.0 ? 0.0 : 1.0;
        }

        return abs($source - $briskly) / abs($briskly);
    }

    /**
     * Хэш source-цен по line_key для anti-tamper перед apply.
     *
     * @param  list<array{line_key: string, price: string}|object>  $sourceLines
     */
    public static function hashSourcePrices(array $sourceLines): string
    {
        $pairs = [];
        foreach ($sourceLines as $line) {
            if (is_array($line)) {
                $key = (string) ($line['line_key'] ?? '');
                $price = self::normalize($line['price'] ?? '0');
            } else {
                $key = (string) ($line->lineKey ?? '');
                $price = self::normalize($line->price ?? '0');
            }
            $pairs[$key] = $price;
        }
        ksort($pairs);

        return hash('sha256', json_encode($pairs, JSON_THROW_ON_ERROR));
    }
}
