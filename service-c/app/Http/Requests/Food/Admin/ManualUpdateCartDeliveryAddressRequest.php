<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\Http\Requests\Food\Concerns\ValidatesDeliveryAddress;

/**
 * Валидация обновления адреса доставки ручной корзины.
 */
class ManualUpdateCartDeliveryAddressRequest extends ManualOrderCustomerFormRequest
{
    use ValidatesDeliveryAddress;

    /**
     * Правила валидации адреса и клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerMaxUserIdRules(),
            ...$this->deliveryAddressRules(),
        ];
    }

    /**
     * Сообщения об ошибках валидации адреса.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->deliveryAddressMessages();
    }

    /**
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->deliveryAddressAttributes();
    }
}
