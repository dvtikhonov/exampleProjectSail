<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация запроса показа/очистки ручной корзины (контекст клиента).
 */
class ShowManualOrderCartRequest extends ManualOrderCustomerFormRequest
{
    /**
     * Правила валидации контекста клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->customerMaxUserIdRules();
    }
}
