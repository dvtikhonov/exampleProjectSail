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

    public function deliveryAddress(): string
    {
        return trim((string) $this->validated('delivery_address'));
    }
}
