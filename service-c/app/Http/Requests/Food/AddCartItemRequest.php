<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use App\Http\Requests\Food\Concerns\ValidatesCartItemPayload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация запроса добавления блюда в корзину.
 */
class AddCartItemRequest extends FormRequest
{
    use ValidatesCartItemPayload;

    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации позиции корзины.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->cartItemRules();
    }

    /**
     * Дополнительная проверка парности полей комбо-метаданных.
     */
    public function withValidator(Validator $validator): void
    {
        $this->cartItemWithValidator($validator);
    }
}
