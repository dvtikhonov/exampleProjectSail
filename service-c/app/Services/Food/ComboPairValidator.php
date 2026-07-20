<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishCatalogRepositoryInterface;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Dish;

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
     * @param  bool  $requirePartnerAvailable  false — для партнёра уже из items_snapshot заказа
     *
     * @throws FoodDomainException
     */
    public function validatePair(Dish $dish, int $partnerDishId, bool $requirePartnerAvailable = true): Dish
    {
        if ($partnerDishId === $dish->id) {
            throw new FoodDomainException('Combo partner dish must differ from the dish being added.');
        }

        $partner = $this->dishRepository->findAvailableWithRestaurant($partnerDishId);

        if ($partner === null) {
            throw new FoodDomainException('Combo partner dish not found.', 404);
        }

        if ($requirePartnerAvailable && ! $partner->is_available) {
            throw new FoodDomainException('Combo partner dish is not available.');
        }

        $dishRestaurantId = $dish->menuCategory->restaurant_id;
        $partnerRestaurantId = $partner->menuCategory->restaurant_id;

        if ($dishRestaurantId !== $partnerRestaurantId) {
            throw new FoodDomainException('Combo dishes must belong to the same restaurant.');
        }

        if ($dish->menu_category_id === $partner->menu_category_id) {
            throw new FoodDomainException('Combo dishes must be from different menu categories.');
        }

        return $partner;
    }
}
