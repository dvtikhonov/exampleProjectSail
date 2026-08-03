<?php

declare(strict_types=1);

namespace App\Services\Food\Composition;

use App\Contracts\Food\Composition\OrderCompositionSnapshotBuilderInterface;
use App\Contracts\Food\Menu\DishCatalogRepositoryInterface;
use App\DTO\Food\Composition\OrderCompositionSnapshotDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Food\Dish;
use App\Models\Max\MaxUser;
use App\Services\Food\Cart\CartTotalsCalculator;
use App\Services\Food\Order\OrderItemsSnapshotBuilder;
use App\Services\Food\Shared\FoodMoneyFormatter;

/**
 * Сборка items_snapshot из dish_id/qty/combo и пересчёт totals по клиенту заказа.
 */
class OrderCompositionSnapshotBuilder implements OrderCompositionSnapshotBuilderInterface
{
    public function __construct(
        private readonly DishCatalogRepositoryInterface $dishRepository,
        private readonly ComboPairValidator $comboPairValidator,
        private readonly OrderItemsSnapshotBuilder $orderItemsSnapshotBuilder,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly FoodMoneyFormatter $moneyFormatter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function build(
        int $restaurantId,
        MaxUser $customer,
        array $items,
        array $existingDishIds = [],
    ): OrderCompositionSnapshotDto {
        $existingDishIdSet = array_fill_keys(
            array_map(static fn (int $id): int => $id, $existingDishIds),
            true,
        );

        $lines = $this->resolveSnapshotLines($restaurantId, $items, $existingDishIdSet);
        $this->assertComboPairsValid($lines, $existingDishIdSet);

        $snapshot = $this->orderItemsSnapshotBuilder->buildFromDishes($lines);

        $totals = $this->cartTotalsCalculator->calculate(
            restaurantId: $restaurantId,
            maxUser: $customer,
            itemsTotal: $snapshot->itemsTotal,
        );

        return new OrderCompositionSnapshotDto(
            itemsSnapshot: $snapshot->itemsSnapshot,
            itemsTotal: $this->moneyFormatter->format($totals->itemsTotal),
            deliveryCost: $totals->deliveryCost !== null
                ? $this->moneyFormatter->format($totals->deliveryCost)
                : null,
            total: $this->moneyFormatter->format($totals->total),
        );
    }

    /**
     * Пакетно загружает блюда из каталога и проверяет доступность и ресторан заказа.
     *
     * @param  list<array{
     *     dish_id: int,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>  $items
     * @param  array<int, true>  $existingDishIdSet
     * @return list<array{
     *     dish: Dish,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>
     *
     * @throws FoodDomainException
     */
    private function resolveSnapshotLines(int $restaurantId, array $items, array $existingDishIdSet): array
    {
        /** @var list<int> $dishIds */
        $dishIds = array_map(static fn (array $item): int => (int) $item['dish_id'], $items);
        $dishesById = $this->dishRepository->findAvailableWithRestaurantByIds($dishIds);

        $lines = [];

        foreach ($items as $item) {
            $dishId = (int) $item['dish_id'];
            $dish = $dishesById->get($dishId);

            if ($dish === null) {
                throw new FoodDomainException('Блюдо не найдено.', 404);
            }

            $wasAlreadyInOrder = isset($existingDishIdSet[$dishId]);

            if (! $wasAlreadyInOrder && ! $dish->is_available) {
                throw new FoodDomainException('Блюдо недоступно.');
            }

            $dishRestaurantId = (int) $dish->menuCategory->restaurant_id;

            if ($dishRestaurantId !== $restaurantId) {
                throw new FoodDomainException('Блюдо не принадлежит ресторану заказа.');
            }

            $lines[] = [
                'dish' => $dish,
                'quantity' => (int) $item['quantity'],
                'combo_ref' => $item['combo_ref'] ?? null,
                'combo_partner_dish_id' => $item['combo_partner_dish_id'] ?? null,
            ];
        }

        return $lines;
    }

    /**
     * Проверяет целостность комбо-пар в новом составе.
     *
     * @param  list<array{
     *     dish: Dish,
     *     quantity: int,
     *     combo_ref: string|null,
     *     combo_partner_dish_id: int|null
     * }>  $lines
     * @param  array<int, true>  $existingDishIdSet
     *
     * @throws FoodDomainException
     */
    private function assertComboPairsValid(array $lines, array $existingDishIdSet): void
    {
        /** @var array<string, list<array{dish: Dish, quantity: int, combo_ref: string|null, combo_partner_dish_id: int|null}>> $groups */
        $groups = [];

        foreach ($lines as $line) {
            if ($line['combo_ref'] === null) {
                continue;
            }

            $groups[$line['combo_ref']][] = $line;
        }

        foreach ($groups as $comboRef => $pair) {
            if (count($pair) !== 2) {
                throw new FoodDomainException(
                    sprintf('Комбо-пара «%s» должна содержать ровно два элемента.', $comboRef),
                );
            }

            [$first, $second] = $pair;

            if ($first['quantity'] !== $second['quantity']) {
                throw new FoodDomainException(
                    sprintf('У элементов комбо-пары «%s» должно быть одинаковое количество.', $comboRef),
                );
            }

            if (
                (int) $first['combo_partner_dish_id'] !== (int) $second['dish']->id
                || (int) $second['combo_partner_dish_id'] !== (int) $first['dish']->id
            ) {
                throw new FoodDomainException(
                    sprintf('ID блюд-партнёров комбо-пары «%s» должны ссылаться друг на друга.', $comboRef),
                );
            }

            $partnerDishId = (int) $first['combo_partner_dish_id'];
            $requirePartnerAvailable = ! isset($existingDishIdSet[$partnerDishId]);

            // Партнёр уже загружен в $lines — без повторного find по id.
            $this->comboPairValidator->assertCompatiblePair(
                $first['dish'],
                $second['dish'],
                $requirePartnerAvailable,
            );
        }
    }
}
