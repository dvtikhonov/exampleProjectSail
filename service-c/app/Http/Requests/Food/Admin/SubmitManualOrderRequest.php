<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация оформления ручного заказа.
 */
class SubmitManualOrderRequest extends ManualOrderCustomerFormRequest
{
    /**
     * Правила валидации клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->customerMaxUserIdRules();
    }
}
