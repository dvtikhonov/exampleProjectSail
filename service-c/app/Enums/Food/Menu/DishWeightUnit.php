<?php

declare(strict_types=1);

namespace App\Enums\Food\Menu;

/**
 * Единица измерения веса или объёма блюда.
 */
enum DishWeightUnit: string
{
    case Gram = 'g';
    case Kilogram = 'kg';
    case Milliliter = 'ml';
    case Liter = 'l';

    /**
     * Краткое обозначение для UI (г, кг, мл, л).
     */
    public function label(): string
    {
        return match ($this) {
            self::Gram => 'г',
            self::Kilogram => 'кг',
            self::Milliliter => 'мл',
            self::Liter => 'л',
        };
    }

    /**
     * Каноническое слово единицы для промпт-контекста (без числа).
     */
    public function canonicalWord(): string
    {
        return match ($this) {
            self::Gram => 'грамм',
            self::Kilogram => 'килограмм',
            self::Milliliter => 'миллилитр',
            self::Liter => 'литр',
        };
    }

    /**
     * Варианты написания единицы без числа (для парсинга свободных строк).
     * Более длинные формы идут первыми — удобно для longest-match.
     *
     * @return list<string>
     */
    public function aliases(): array
    {
        return match ($this) {
            self::Gram => ['грамм', 'гр.', 'гр', 'г.', 'г'],
            self::Kilogram => ['килограмм', 'кг.', 'кг'],
            self::Milliliter => ['миллилитр', 'мл.', 'мл'],
            self::Liter => ['литр', 'л.', 'л'],
        };
    }

    /**
     * Распознаёт единицу по алиасу (с опциональной точкой, без учёта регистра).
     */
    public static function tryFromAlias(string $alias): ?self
    {
        $normalized = mb_strtolower(trim($alias));
        if ($normalized === '') {
            return null;
        }

        $normalized = rtrim($normalized, '.');

        foreach (self::cases() as $case) {
            foreach ($case->aliases() as $candidate) {
                if (mb_strtolower(rtrim($candidate, '.')) === $normalized) {
                    return $case;
                }
            }
        }

        return null;
    }
}
