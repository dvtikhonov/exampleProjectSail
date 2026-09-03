<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\DTO\Food\Shared\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;

/**
 * Резолв ровно одного клиента MAX по подстроке имени для ручного заказа.
 */
interface ManualOrderCustomerResolverInterface
{
    /**
     * Находит ровно одного клиента по нормализованной подстроке в first_name / last_name / username.
     *
     * @throws FoodDomainException 0 или >1 совпадений — STOP
     */
    public function resolveExactlyOne(string $customerQuery): MaxUserIdentity;
}
