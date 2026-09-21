<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Общие правила и accessors для адреса доставки корзины.
 *
 * @mixin FormRequest
 */
trait ValidatesDeliveryAddress
{
    /**
     * Правила валидации поля delivery_address.
     *
     * @return array<string, array<int, string>>
     */
    protected function deliveryAddressRules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * Сообщения об ошибках валидации адреса.
     *
     * @return array<string, string>
     */
    protected function deliveryAddressMessages(): array
    {
        return [
            'delivery_address.required' => 'Укажите адрес доставки.',
            'delivery_address.max' => 'Адрес доставки не должен превышать 1000 символов.',
        ];
    }

    /**
     * Человекочитаемые имена атрибутов адреса доставки.
     *
     * @return array<string, string>
     */
    protected function deliveryAddressAttributes(): array
    {
        return [
            'delivery_address' => 'адрес доставки',
        ];
    }

    /**
     * Проверяет, что адрес доставки не состоит только из пробелов.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $address = $this->input('delivery_address');

            if (! is_string($address) || trim($address) === '') {
                $validator->errors()->add('delivery_address', 'Укажите адрес доставки.');
            }
        });
    }

    /**
     * Возвращает нормализованный адрес доставки.
     */
    public function deliveryAddress(): string
    {
        return trim((string) $this->validated('delivery_address'));
    }
}
