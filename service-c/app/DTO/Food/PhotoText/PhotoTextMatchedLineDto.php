<?php

declare(strict_types=1);

namespace App\DTO\Food\PhotoText;

/**
 * Бесспорно сматченная позиция PhotoText для ручной корзины.
 */
readonly class PhotoTextMatchedLineDto
{
    public function __construct(
        public string $rawTitle,
        public int $quantity,
        public int $dishId,
        public string $dishName,
        public ?int $comboPartnerDishId = null,
        public ?string $comboPartnerDishName = null,
        public ?string $comboRef = null,
        public ?int $restaurantId = null,
    ) {}

    /**
     * Пара блюд комбо.
     */
    public function isCombo(): bool
    {
        return $this->comboPartnerDishId !== null;
    }

    /**
     * @return array<string, int|string|null|bool>
     */
    public function toArray(): array
    {
        return [
            'raw_title' => $this->rawTitle,
            'quantity' => $this->quantity,
            'dish_id' => $this->dishId,
            'dish_name' => $this->dishName,
            'combo_partner_dish_id' => $this->comboPartnerDishId,
            'combo_partner_dish_name' => $this->comboPartnerDishName,
            'combo_ref' => $this->comboRef,
            'is_combo' => $this->isCombo(),
        ];
    }
}
