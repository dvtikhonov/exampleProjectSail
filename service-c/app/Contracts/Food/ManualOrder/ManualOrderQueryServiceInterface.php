<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\DTO\Food\ManualOrder\ManualOrderDetailDto;
use App\DTO\Food\ManualOrder\ManualOrderListItemDto;
use App\Enums\Food\Order\OrderStatus;
use App\Exceptions\Food\FoodDomainException;

/**
 * Выборка ручных заказов для роли max_manager.
 */
interface ManualOrderQueryServiceInterface
{
    /**
     * Постраничный список ручных заказов с фильтром по потребителю, периоду, статусу и/или ФИО.
     *
     * @return array{
     *     orders: list<ManualOrderListItemDto>,
     *     meta: array{
     *         current_page: int,
     *         per_page: int,
     *         total: int,
     *         last_page: int,
     *         total_amount: string
     *     }
     * }
     */
    public function list(
        ?string $query,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        ?int $customerMaxUserId = null,
        ?OrderStatus $status = null,
    ): array;

    /**
     * Детальные данные ручного заказа по идентификатору.
     *
     * @throws FoodDomainException
     */
    public function show(int $orderId): ManualOrderDetailDto;
}
