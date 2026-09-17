<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Concerns;

use App\Exceptions\Food\FoodDomainException;
use App\Http\Support\CustomerResourceOwnershipGuard;
use App\Models\Max\MaxUser;

/**
 * Общая логика authorize() для customer FormRequest с resource-ID.
 */
trait AuthorizesCustomerResourceOwnership
{
    /**
     * HTTP-адаптер ownership (Eloquent допускается на границе Http/).
     */
    protected function ownershipGuard(): CustomerResourceOwnershipGuard
    {
        return $this->container->make(CustomerResourceOwnershipGuard::class);
    }

    /**
     * Аутентифицированный MaxUser или null.
     */
    protected function authenticatedMaxUser(): ?MaxUser
    {
        $user = $this->user();

        return $user instanceof MaxUser ? $user : null;
    }

    /**
     * ID ресурса из route-параметра.
     */
    protected function routeResourceId(string $parameter): int
    {
        return (int) $this->route($parameter);
    }

    /**
     * Единый JSON 403 для отказа authorize() (как у FoodDomainException).
     *
     * @throws FoodDomainException
     */
    protected function failedAuthorization(): void
    {
        throw new FoodDomainException('Доступ запрещён.', 403);
    }
}
