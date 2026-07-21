<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

/**
 * Валидация обновления адреса доставки ручной корзины.
 */
class ManualUpdateCartDeliveryAddressRequest extends ManualOrderCustomerFormRequest
{
    /**
     * Правила валидации адреса и клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerMaxUserIdRules(),
            'delivery_address' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Сообщения об ошибках валидации адреса.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delivery_address.required' => 'Укажите адрес доставки.',
            'delivery_address.max' => 'Адрес доставки не должен превышать 1000 символов.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'delivery_address' => 'адрес доставки',
        ];
    }

    /**
     * Возвращает нормализованный адрес доставки.
     */
    public function deliveryAddress(): string
    {
        return trim((string) $this->validated('delivery_address'));
    }
}
