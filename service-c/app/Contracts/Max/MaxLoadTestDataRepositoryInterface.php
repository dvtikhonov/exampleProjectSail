<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Data-access для нагрузочного стенда MAX (вне use-case Eloquent).
 */
interface MaxLoadTestDataRepositoryInterface
{
    /**
     * Id активных ресторанов по возрастанию.
     *
     * @return list<int>
     */
    public function listActiveRestaurantIds(): array;

    /**
     * Включает недоступные блюда активных ресторанов.
     *
     * @param  list<int>  $restaurantIds
     * @return int Число обновлённых блюд
     */
    public function enableUnavailableDishesForRestaurants(array $restaurantIds): int;

    /**
     * Удаляет заказы пользователей нагрузочного стенда.
     *
     * @param  list<int>  $maxUserIds
     */
    public function deleteOrdersForMaxUserIds(array $maxUserIds): int;

    /**
     * Удаляет корзины пользователей нагрузочного стенда.
     *
     * @param  list<int>  $maxUserIds
     */
    public function deleteCartsForMaxUserIds(array $maxUserIds): int;
}
