<?php

declare(strict_types=1);

namespace App\Contracts\Food\Order;

/**
 * Полный порт чтения заказов еды для административного API.
 *
 * Composition ISP: объединяет review / manual порты.
 */
interface FoodOrderAdminReadRepositoryInterface extends FoodOrderAdminReviewReadRepositoryInterface, FoodOrderManualAdminReadRepositoryInterface {}
