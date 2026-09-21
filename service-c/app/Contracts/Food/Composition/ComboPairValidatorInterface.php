<?php

declare(strict_types=1);

namespace App\Contracts\Food\Composition;

use App\DTO\Food\Menu\DishRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Валидация пары блюд для комбо: доступность, ресторан, разные категории с is_combo_available.
 */
interface ComboPairValidatorInterface
{
    /**
     * Проверяет, что партнёр комбо допустим для указанного блюда.
     *
     * @param  bool  $requirePartnerAvailable  false — партнёр из items_snapshot или ручной заказ
     *
     * @throws FoodDomainException
     */
    public function validatePair(
        DishRecord $dish,
        int $partnerDishId,
        bool $requirePartnerAvailable = true,
    ): DishRecord;

    /**
     * Проверяет совместимость уже загруженных блюд комбо-пары (без доп. запросов к БД).
     *
     * @param  bool  $requirePartnerAvailable  false — партнёр из items_snapshot или ручной заказ
     *
     * @throws FoodDomainException
     */
    public function assertCompatiblePair(
        DishRecord $dish,
        DishRecord $partner,
        bool $requirePartnerAvailable = true,
    ): void;
}
