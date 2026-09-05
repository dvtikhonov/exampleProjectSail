<?php

declare(strict_types=1);

namespace App\Contracts\Food\Cart;

/**
 * Полный репозиторий корзины пользователя MAX mini-app.
 *
 * Composition ISP: объединяет draft / item / lifecycle порты.
 */
interface CartRepositoryInterface extends
    CartDraftRepositoryInterface,
    CartItemRepositoryInterface,
    CartLifecycleRepositoryInterface
{
}
