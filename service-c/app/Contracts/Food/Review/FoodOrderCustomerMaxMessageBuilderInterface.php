<?php

declare(strict_types=1);

namespace App\Contracts\Food\Review;

/**
 * Composition ISP: полный порт сборки текстов MAX-уведомлений о заказе.
 *
 * Предпочтительнее инжектить узкий порт:
 * {@see FoodOrderCustomerStatusMaxMessageBuilderInterface},
 * {@see FoodOrderCustomerCompositionMaxMessageBuilderInterface},
 * {@see FoodOrderManualCreatorMaxMessageBuilderInterface},
 * {@see FoodOrderUiStandNewRequestMaxMessageBuilderInterface}.
 */
interface FoodOrderCustomerMaxMessageBuilderInterface extends FoodOrderCustomerCompositionMaxMessageBuilderInterface, FoodOrderCustomerStatusMaxMessageBuilderInterface, FoodOrderManualCreatorMaxMessageBuilderInterface, FoodOrderUiStandNewRequestMaxMessageBuilderInterface {}
