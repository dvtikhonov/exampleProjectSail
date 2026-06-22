<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCartDeliveryAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
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
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'delivery_address' => 'адрес доставки',
        ];
    }

    public function deliveryAddress(): string
    {
        return trim((string) $this->validated('delivery_address'));
    }
}
