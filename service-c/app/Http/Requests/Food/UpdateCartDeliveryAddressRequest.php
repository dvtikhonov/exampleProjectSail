<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Http\Requests\Food\Concerns\ValidatesDeliveryAddress;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса обновления адреса доставки корзины.
 */
class UpdateCartDeliveryAddressRequest extends FormRequest
{
    use ValidatesDeliveryAddress;

    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации адреса доставки.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->deliveryAddressRules();
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
