<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\DishImageUrlResolverInterface;
use App\DTO\Food\OrderDto;
use App\Enums\Food\CartStatus;
use App\Enums\Food\OrderStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\Cart;
use App\Models\FoodOrder;
use App\Models\MaxUser;
use Illuminate\Support\Facades\DB;

class OrderSubmissionService
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
    ) {}

    public function submit(MaxUser $maxUser): OrderDto
    {
        return DB::transaction(function () use ($maxUser): OrderDto {
            $cart = Cart::query()
                ->where('max_user_id', $maxUser->max_user_id)
                ->where('status', CartStatus::Draft)
                ->with(['restaurant', 'items.dish'])
                ->lockForUpdate()
                ->first();

            if ($cart === null || $cart->items->isEmpty()) {
                throw new FoodDomainException('Cart is empty.');
            }

            $itemsSnapshot = [];
            $total = 0.0;

            foreach ($cart->items as $item) {
                $unitPrice = (float) $item->dish->price;
                $lineTotal = $unitPrice * $item->quantity;
                $total += $lineTotal;

                $itemsSnapshot[] = [
                    'dish_id' => $item->dish_id,
                    'dish_name' => $item->dish->name,
                    'unit_price' => $this->moneyFormatter->format($unitPrice),
                    'quantity' => $item->quantity,
                    'line_total' => $this->moneyFormatter->format($lineTotal),
                    'image_url' => $this->imageUrlResolver->resolve($item->dish->image_url),
                ];
            }

            $order = FoodOrder::query()->create([
                'cart_id' => $cart->id,
                'max_user_id' => $maxUser->max_user_id,
                'restaurant_id' => $cart->restaurant_id,
                'status' => OrderStatus::Submitted,
                'total' => $this->moneyFormatter->format($total),
                'items_snapshot' => $itemsSnapshot,
            ]);

            $cart->update(['status' => CartStatus::Submitted]);

            return new OrderDto(
                id: $order->id,
                status: $order->status->value,
                restaurantId: $order->restaurant_id,
                restaurantName: $cart->restaurant->name,
                total: $this->moneyFormatter->format($order->total),
                itemsSnapshot: $itemsSnapshot,
                createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
            );
        });
    }
}
