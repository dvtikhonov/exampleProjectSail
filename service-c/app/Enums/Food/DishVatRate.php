<?php

declare(strict_types=1);

namespace App\Enums\Food;

/**
 * Ставка НДС для блюда. {@see self::Exempt} соответствует NULL в БД.
 */
enum DishVatRate
{
    case Exempt;
    case Five;
    case Seven;
    case Ten;
    case Twenty;
    case TwentyTwo;

    /**
     * Значение ставки для хранения в БД (NULL — не облагается НДС).
     */
    public function value(): ?int
    {
        return match ($this) {
            self::Exempt => null,
            self::Five => 5,
            self::Seven => 7,
            self::Ten => 10,
            self::Twenty => 20,
            self::TwentyTwo => 22,
        };
    }

    /**
     * Человекочитаемая подпись для UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Exempt => 'Не облагается НДС',
            self::Five => '5%',
            self::Seven => '7%',
            self::Ten => '10%',
            self::Twenty => '20%',
            self::TwentyTwo => '22%',
        };
    }

    /**
     * @return list<self>
     */
    public static function selectableCases(): array
    {
        return self::cases();
    }

    /**
     * Преобразует значение из БД в enum.
     */
    public static function fromValue(?int $value): self
    {
        return match ($value) {
            null => self::Exempt,
            5 => self::Five,
            7 => self::Seven,
            10 => self::Ten,
            20 => self::Twenty,
            22 => self::TwentyTwo,
            default => throw new \ValueError("Недопустимая ставка НДС: {$value}"),
        };
    }
}
