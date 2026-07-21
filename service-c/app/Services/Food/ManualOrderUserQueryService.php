<?php

declare(strict_types=1);

namespace App\Services\Food;

use App\Contracts\Food\ManualOrderUserQueryServiceInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Food\ManualOrderUserDto;
use App\Exceptions\Food\FoodDomainException;
use App\Models\MaxUser;

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
                static fn (MaxUser $user): ManualOrderUserDto => new ManualOrderUserDto(
                    maxUserId: $user->max_user_id,
                    firstName: $user->first_name,
                    lastName: $user->last_name,
                    username: $user->username,
                    deliveryAddress: $user->delivery_address,
                ),
                $paginator->items(),
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function findCustomerOrFail(int $maxUserId): MaxUser
    {
        $customer = $this->maxUserRepository->findByMaxUserId($maxUserId);

        if ($customer === null) {
            throw new FoodDomainException('User not found.', 404);
        }

        return $customer;
    }
}
