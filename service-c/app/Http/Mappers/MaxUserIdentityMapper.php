<?php

declare(strict_types=1);

namespace App\Http\Mappers;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\DTO\Food\Shared\MaxUserIdentity;
use App\Models\Max\MaxUser;

/**
 * Маппинг аутентифицированного MaxUser в доменную идентичность на HTTP-границе.
 */
class MaxUserIdentityMapper
{
    public function __construct(
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Преобразует Eloquent MaxUser в {@see MaxUserIdentity}.
     */
    public function fromModel(MaxUser $user): MaxUserIdentity
    {
        return new MaxUserIdentity(
            maxUserId: $user->max_user_id,
            adminRoles: $this->foodOrderAdminRepository->getActiveRoles($user->max_user_id),
        );
    }
}
