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
use App\Services\Max\MaxUserDeliveryAddressService;
use Illuminate\Support\Facades\DB;

class OrderSubmissionService
{
    public function __construct(
        private readonly FoodMoneyFormatter $moneyFormatter,
        private readonly DishImageUrlResolverInterface $imageUrlResolver,
        private readonly CartTotalsCalculator $cartTotalsCalculator,
        private readonly MaxUserDeliveryAddressService $maxUserDeliveryAddressService,
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

            if ($cart->delivery_address === null || trim($cart->delivery_address) === '') {
                throw new FoodDomainException('Delivery address is required.');
            }

            $itemsSnapshot = [];
            $itemsTotal = 0.0;

            foreach ($cart->items as $item) {
                $unitPrice = (float) $item->dish->price;
                $lineTotal = $unitPrice * $item->quantity;
                $itemsTotal += $lineTotal;

                $itemsSnapshot[] = [
                    'dish_id' => $item->dish_id,
                    'dish_name' => $item->dish->name,
                    'unit_price' => $this->moneyFormatter->format($unitPrice),
                    'quantity' => $item->quantity,
                    'line_total' => $this->moneyFormatter->format($lineTotal),
                    'image_url' => $this->imageUrlResolver->resolve($item->dish->image_url),
                ];
            }

            $totals = $this->cartTotalsCalculator->calculate(
                restaurantId: $cart->restaurant_id,
                maxUser: $maxUser,
                itemsTotal: $itemsTotal,
            );

            $formattedItemsTotal = $this->moneyFormatter->format($totals->itemsTotal);
            $formattedDeliveryCost = $totals->deliveryCost !== null
                ? $this->moneyFormatter->format($totals->deliveryCost)
                : null;
            $formattedTotal = $this->moneyFormatter->format($totals->total);

            $this->maxUserDeliveryAddressService->persist($maxUser, $cart->delivery_address);

            $order = FoodOrder::query()->create([
                'cart_id' => $cart->id,
                'max_user_id' => $maxUser->max_user_id,
                'restaurant_id' => $cart->restaurant_id,
                'status' => OrderStatus::Submitted,
                'total' => $formattedTotal,
                'delivery_address' => $cart->delivery_address,
                'delivery_cost' => $formattedDeliveryCost,
                'items_total' => $formattedItemsTotal,
                'items_snapshot' => $itemsSnapshot,
            ]);

            $cart->update(['status' => CartStatus::Submitted]);

            return new OrderDto(
                id: $order->id,
                status: $order->status->value,
                restaurantId: $order->restaurant_id,
                restaurantName: $cart->restaurant->name,
                itemsTotal: $formattedItemsTotal,
                deliveryApplicable: $totals->deliveryApplicable,
                deliveryCost: $formattedDeliveryCost,
                total: $formattedTotal,
                deliveryAddress: $cart->delivery_address,
                itemsSnapshot: $itemsSnapshot,
                createdAt: $order->created_at?->toIso8601String() ?? now()->toIso8601String(),
            );
        });
    }
}
