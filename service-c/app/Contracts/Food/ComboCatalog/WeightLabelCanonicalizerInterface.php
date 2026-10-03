<?php

declare(strict_types=1);

namespace App\Contracts\Food\ComboCatalog;

use App\DTO\Food\Menu\DishRecord;
use App\Enums\Food\Menu\DishWeightUnit;

/**
 * Канонизация подписи веса для промпт-контекста («120 грамм»).
 */
interface WeightLabelCanonicalizerInterface
{
    /**
     * Канон из DishRecord; пустой вес → null.
     */
    public function fromDish(DishRecord $dish): ?string;

    /**
     * Канон из числа и единицы: «120 грамм».
     */
    public function fromAmountAndUnit(string|int|float $amount, DishWeightUnit $unit): string;

    /**
     * Канон из свободной строки («120г», «120 г.», «120 грамм»); нераспознанное → null.
     */
    public function fromLabel(?string $raw): ?string;
}
