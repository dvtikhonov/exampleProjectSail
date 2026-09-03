<?php

declare(strict_types=1);

namespace App\Services\Food\ManualOrder;

use App\Contracts\Food\ManualOrder\ManualOrderUserQueryServiceInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Food\ManualOrder\ManualOrderUserDto;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\DTO\Max\MaxUserRecord;
use App\Exceptions\Food\FoodDomainException;

/**
 * Поиск клиентов MAX для оформления ручного заказа.
 */
class ManualOrderUserQueryService implements ManualOrderUserQueryServiceInterface
{
    public function __construct(
        private readonly MaxUserRepositoryInterface $maxUserRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function list(?string $query, int $perPage): array
    {
        $paginator = $this->maxUserRepository->paginateForManualOrders($query, $perPage);

        return [
            'users' => array_map(
                static fn (MaxUserRecord $user): ManualOrderUserDto => new ManualOrderUserDto(
                    maxUserId: $user->maxUserId,
                    firstName: $user->firstName,
                    lastName: $user->lastName,
                    username: $user->username,
                    deliveryAddress: $user->deliveryAddress,
                ),
                $paginator->items,
            ),
            'meta' => [
                'current_page' => $paginator->currentPage,
                'per_page' => $paginator->perPage,
                'total' => $paginator->total,
                'last_page' => $paginator->lastPage,
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findCustomerOrFail(int $maxUserId): MaxUserIdentity
    {
        $customer = $this->maxUserRepository->findByMaxUserId($maxUserId);

        if ($customer === null) {
            throw new FoodDomainException('Пользователь не найден.', 404);
        }

        return new MaxUserIdentity(
            maxUserId: $customer->maxUserId,
            adminRoles: [],
        );
    }
}
