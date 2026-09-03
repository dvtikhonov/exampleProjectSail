<?php

declare(strict_types=1);

namespace App\Repositories\Food\Cart;

use App\DTO\Food\Cart\CartCreateCommand;
use App\DTO\Food\Cart\CartItemCreateCommand;
use App\DTO\Food\Cart\CartItemRecord;
use App\DTO\Food\Cart\CartRecord;
use App\Models\Food\Cart;
use App\Models\Food\CartItem;
use App\Repositories\Food\Menu\DishMapper;

/**
 * Преобразование между Eloquent-моделями корзины и доменными Record/Command.
 */
class CartMapper
{
    public function __construct(
        private readonly DishMapper $dishMapper,
    ) {}

    /**
     * Преобразует модель корзины в доменную проекцию.
     */
    public function toRecord(Cart $model): CartRecord
    {
        $items = [];

        if ($model->relationLoaded('items')) {
            foreach ($model->items as $item) {
                $items[] = $this->toItemRecord($item, $model);
            }
        }

        return new CartRecord(
            id: (int) $model->id,
            maxUserId: (int) $model->max_user_id,
            createdByMaxUserId: $model->created_by_max_user_id !== null
                ? (int) $model->created_by_max_user_id
                : null,
            restaurantId: (int) $model->restaurant_id,
            status: $model->status,
            deliveryAddress: $model->delivery_address,
            restaurantName: $model->relationLoaded('restaurant')
                ? ($model->restaurant?->name !== null ? (string) $model->restaurant->name : null)
                : null,
            items: $items,
        );
    }

    /**
     * Преобразует позицию корзины в доменную проекцию.
     */
    public function toItemRecord(CartItem $model, ?Cart $cart = null): CartItemRecord
    {
        $resolvedCart = $cart ?? ($model->relationLoaded('cart') ? $model->cart : null);

        return new CartItemRecord(
            id: (int) $model->id,
            cartId: (int) $model->cart_id,
            dishId: (int) $model->dish_id,
            quantity: (int) $model->quantity,
            comboRef: $model->combo_ref,
            comboPartnerDishId: $model->combo_partner_dish_id !== null
                ? (int) $model->combo_partner_dish_id
                : null,
            dish: $model->relationLoaded('dish') && $model->dish !== null
                ? $this->dishMapper->toRecord($model->dish)
                : null,
            comboPartnerDishName: $model->relationLoaded('comboPartnerDish')
                ? ($model->comboPartnerDish?->name !== null ? (string) $model->comboPartnerDish->name : null)
                : null,
            cartMaxUserId: $resolvedCart !== null ? (int) $resolvedCart->max_user_id : null,
            cartCreatedByMaxUserId: $resolvedCart !== null && $resolvedCart->created_by_max_user_id !== null
                ? (int) $resolvedCart->created_by_max_user_id
                : null,
            cartStatus: $resolvedCart?->status,
            cartRestaurantId: $resolvedCart !== null ? (int) $resolvedCart->restaurant_id : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toCreateAttributes(CartCreateCommand $command): array
    {
        return [
            'max_user_id' => $command->maxUserId,
            'created_by_max_user_id' => $command->createdByMaxUserId,
            'restaurant_id' => $command->restaurantId,
            'status' => $command->status,
            'delivery_address' => $command->deliveryAddress,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toItemCreateAttributes(CartItemCreateCommand $command): array
    {
        return [
            'cart_id' => $command->cartId,
            'dish_id' => $command->dishId,
            'quantity' => $command->quantity,
            'combo_ref' => $command->comboRef,
            'combo_partner_dish_id' => $command->comboPartnerDishId,
        ];
    }
}
