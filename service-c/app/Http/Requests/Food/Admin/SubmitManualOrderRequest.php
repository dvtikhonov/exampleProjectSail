<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация оформления ручного заказа.
 *
 * Валидируется: max_user_id (клиент), опционально delivery_date (Y-m-d).
 */
class SubmitManualOrderRequest extends ManualOrderCustomerFormRequest
{
    /**
     * Правила валидации клиента и даты доставки.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerMaxUserIdRules(),
            'delivery_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Явная дата доставки из тела запроса (Y-m-d) или null — дата доступности меню.
     */
    public function deliveryDate(): ?string
    {
        $value = $this->validated('delivery_date');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
