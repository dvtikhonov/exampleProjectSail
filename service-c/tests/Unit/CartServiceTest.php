<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\Food\CartStatus;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;
use App\Services\Food\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FoodTestDataBuilder;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_draft_cart_returns_null_when_missing(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_001,
            'first_name' => 'Cart',
        ]);

        $cart = app(CartService::class)->getDraftCart($maxUser);

        $this->assertNull($cart);
    }

    public function test_add_item_throws_when_dish_not_found(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_002,
            'first_name' => 'Cart',
        ]);

        $this->expectException(FoodDomainException::class);
        $this->expectExceptionMessage('Dish not found.');

        app(CartService::class)->addItem($maxUser, 99_999, 1);
    }

    public function test_add_item_creates_cart_with_expected_total(): void
    {
        $maxUser = MaxUser::query()->create([
            'max_user_id' => 11_003,
            'first_name' => 'Cart',
        ]);

        $fixture = FoodTestDataBuilder::createRestaurantWithDish(price: 125.25);

        $cart = app(CartService::class)->addItem($maxUser, $fixture['dish']->id, 2);

        $this->assertSame(CartStatus::Draft->value, $cart->status);
        $this->assertSame('250.50', $cart->total);
        $this->assertCount(1, $cart->items);
    }
}
