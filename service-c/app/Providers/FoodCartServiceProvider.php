<?php

namespace App\Providers;

use App\Contracts\Food\Cart\CartDeliveryAddressServiceInterface;
use App\Contracts\Food\Cart\CartDraftRepositoryInterface;
use App\Contracts\Food\Cart\CartDtoFactoryInterface;
use App\Contracts\Food\Cart\CartItemMutationCoordinatorInterface;
use App\Contracts\Food\Cart\CartItemRepositoryInterface;
use App\Contracts\Food\Cart\CartLifecycleRepositoryInterface;
use App\Contracts\Food\Cart\CartRepositoryInterface;
use App\Contracts\Food\Cart\CartServiceInterface;
use App\Contracts\Food\Cart\CartTotalsCalculatorInterface;
use App\Contracts\Food\Delivery\DeliveryCostResolverInterface;
use App\Repositories\Food\Cart\EloquentCartDraftRepository;
use App\Repositories\Food\Cart\EloquentCartItemRepository;
use App\Repositories\Food\Cart\EloquentCartLifecycleRepository;
use App\Repositories\Food\Cart\EloquentCartRepository;
use App\Services\Food\Cart\CartDeliveryAddressService;
use App\Services\Food\Cart\CartDtoFactory;
use App\Services\Food\Cart\CartItemMutationCoordinator;
use App\Services\Food\Cart\CartService;
use App\Services\Food\Cart\CartTotalsCalculator;
use App\Services\Food\Delivery\DeliveryCostResolver;
use Illuminate\Support\ServiceProvider;

/**
 * DI-привязки Food Cart.
 */
class FoodCartServiceProvider extends ServiceProvider
{
    /**
     * Регистрирует контракты и сервисы корзины.
     */
    public function register(): void
    {
        $this->app->bind(CartDraftRepositoryInterface::class, EloquentCartDraftRepository::class);
        $this->app->bind(CartItemRepositoryInterface::class, EloquentCartItemRepository::class);
        $this->app->bind(CartLifecycleRepositoryInterface::class, EloquentCartLifecycleRepository::class);
        $this->app->bind(CartRepositoryInterface::class, EloquentCartRepository::class);
        $this->app->bind(CartServiceInterface::class, CartService::class);
        $this->app->bind(CartTotalsCalculatorInterface::class, CartTotalsCalculator::class);
        $this->app->bind(DeliveryCostResolverInterface::class, DeliveryCostResolver::class);
        $this->app->bind(CartDeliveryAddressServiceInterface::class, CartDeliveryAddressService::class);
        $this->app->bind(CartDtoFactoryInterface::class, CartDtoFactory::class);
        $this->app->bind(CartItemMutationCoordinatorInterface::class, CartItemMutationCoordinator::class);
    }
}
