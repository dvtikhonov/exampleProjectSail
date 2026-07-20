<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishCatalogRepositoryInterface;
use App\Contracts\Food\OrderCompositionSnapshotBuilderInterface;
use App\DTO\Food\OrderCompositionSnapshotDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Dish;
use App\Models\MaxUser;

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
     * Загружает блюда из каталога и проверяет доступность и ресторан заказа.
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
        $lines = [];

        foreach ($items as $item) {
            $dishId = (int) $item['dish_id'];
            $dish = $this->dishRepository->findAvailableWithRestaurant($dishId);

            if ($dish === null) {
                throw new FoodDomainException('Dish not found.', 404);
            }

            $wasAlreadyInOrder = isset($existingDishIdSet[$dishId]);

            if (! $wasAlreadyInOrder && ! $dish->is_available) {
                throw new FoodDomainException('Dish is not available.');
            }

            $dishRestaurantId = (int) $dish->menuCategory->restaurant_id;

            if ($dishRestaurantId !== $restaurantId) {
                throw new FoodDomainException('Dish does not belong to the order restaurant.');
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
                    sprintf('Combo pair "%s" must contain exactly two items.', $comboRef),
                );
            }

            [$first, $second] = $pair;

            if ($first['quantity'] !== $second['quantity']) {
                throw new FoodDomainException(
                    sprintf('Combo pair "%s" items must have the same quantity.', $comboRef),
                );
            }

            if (
                (int) $first['combo_partner_dish_id'] !== (int) $second['dish']->id
                || (int) $second['combo_partner_dish_id'] !== (int) $first['dish']->id
            ) {
                throw new FoodDomainException(
                    sprintf('Combo pair "%s" partner dish ids must reference each other.', $comboRef),
                );
            }

            $partnerDishId = (int) $first['combo_partner_dish_id'];
            $requirePartnerAvailable = ! isset($existingDishIdSet[$partnerDishId]);

            $this->comboPairValidator->validatePair(
                $first['dish'],
                $partnerDishId,
                $requirePartnerAvailable,
            );
        }
    }
}
