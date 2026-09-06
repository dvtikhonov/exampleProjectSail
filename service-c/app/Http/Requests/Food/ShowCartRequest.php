<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса текущей корзины.
 *
 * Тело запроса не требуется; доступ — middleware auth.
 */
class ShowCartRequest extends FormRequest
{
    /**
     * Разрешает запрос (доступ пользователя — middleware).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Всегда ожидает JSON-ответ.
     */
    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * Тело запроса пустое.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
