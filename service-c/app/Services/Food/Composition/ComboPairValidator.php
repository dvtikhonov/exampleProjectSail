<?php

declare(strict_types=1);

namespace App\Services\Food\Composition;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\DTO\Food\Menu\DishRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Валидация пары блюд для комбо: доступность, ресторан, разные категории с is_combo_available.
 */
class ComboPairValidator
{
    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
    ) {}

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
    ): DishRecord {
        $partner = $this->dishRepository->findAvailableWithRestaurant($partnerDishId);

        if ($partner === null) {
            throw new FoodDomainException('Блюдо-партнёр комбо не найдено.', 404);
        }

        $this->assertCompatiblePair($dish, $partner, $requirePartnerAvailable);

        return $partner;
    }

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
    ): void {
        if ($partner->id === $dish->id) {
            throw new FoodDomainException('Блюдо-партнёр комбо должно отличаться от добавляемого блюда.');
        }

        if ($requirePartnerAvailable && ! $partner->isAvailable) {
            throw new FoodDomainException('Блюдо-партнёр комбо недоступно.');
        }

        $dishRestaurantId = $dish->menuCategory?->restaurantId;
        $partnerRestaurantId = $partner->menuCategory?->restaurantId;

        if ($dishRestaurantId === null || $partnerRestaurantId === null) {
            throw new FoodDomainException('Блюда комбо должны принадлежать одному ресторану.');
        }

        if ($dishRestaurantId !== $partnerRestaurantId) {
            throw new FoodDomainException('Блюда комбо должны принадлежать одному ресторану.');
        }

        if ($dish->menuCategoryId === $partner->menuCategoryId) {
            throw new FoodDomainException('Блюда комбо должны быть из разных категорий меню.');
        }

        if (! ($dish->menuCategory?->isComboAvailable ?? false)) {
            throw new FoodDomainException('Категория первого блюда не поддерживает режим комбо.');
        }

        if (! ($partner->menuCategory?->isComboAvailable ?? false)) {
            throw new FoodDomainException('Категория блюда-партнёра не поддерживает режим комбо.');
        }
    }
}
