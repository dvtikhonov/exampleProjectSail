<?php

declare(strict_types=1);

namespace App\Contracts\Food\ManualOrder;

use App\Exceptions\Food\FoodDomainException;
use App\Models\Max\MaxUser;

/**
 * Резолв ровно одного клиента MAX по подстроке имени для ручного заказа.
 */
interface ManualOrderCustomerResolverInterface
{
    /**
     * Находит ровно одного MaxUser по нормализованной подстроке в first_name / last_name / username.
     *
     * @throws FoodDomainException 0 или >1 совпадений — STOP
     */
    public function resolveExactlyOne(string $customerQuery): MaxUser;
}
