<?php

declare(strict_types=1);

namespace App\Services\Food\Composition;

use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Dish;

/**
 * Валидация пары блюд для комбо: доступность, ресторан, разные категории.
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
    public function validatePair(Dish $dish, int $partnerDishId, bool $requirePartnerAvailable = true): Dish
    {
        if ($partnerDishId === $dish->id) {
            throw new FoodDomainException('Блюдо-партнёр комбо должно отличаться от добавляемого блюда.');
        }

        $partner = $this->dishRepository->findAvailableWithRestaurant($partnerDishId);

        if ($partner === null) {
            throw new FoodDomainException('Блюдо-партнёр комбо не найдено.', 404);
        }

        if ($requirePartnerAvailable && ! $partner->is_available) {
            throw new FoodDomainException('Блюдо-партнёр комбо недоступно.');
        }

        $dishRestaurantId = $dish->menuCategory->restaurant_id;
        $partnerRestaurantId = $partner->menuCategory->restaurant_id;

        if ($dishRestaurantId !== $partnerRestaurantId) {
            throw new FoodDomainException('Блюда комбо должны принадлежать одному ресторану.');
        }

        if ($dish->menu_category_id === $partner->menu_category_id) {
            throw new FoodDomainException('Блюда комбо должны быть из разных категорий меню.');
        }

        return $partner;
    }
}
