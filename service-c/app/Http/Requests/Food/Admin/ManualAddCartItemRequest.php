<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\Http\Requests\Food\Concerns\ValidatesCartItemPayload;
use Illuminate\Validation\Validator;

/**
 * Валидация добавления блюда в ручную корзину.
 */
class ManualAddCartItemRequest extends ManualOrderCustomerFormRequest
{
    use ValidatesCartItemPayload;

    /**
     * Правила валидации позиции корзины и клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            ...$this->customerMaxUserIdRules(),
            ...$this->cartItemRules(),
        ];
    }

    /**
     * Дополнительная проверка парности полей комбо-метаданных.
     */
    public function withValidator(Validator $validator): void
    {
        $this->cartItemWithValidator($validator);
    }
}
