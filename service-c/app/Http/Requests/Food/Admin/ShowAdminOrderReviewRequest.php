<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров карточки заказа для админ-проверки.
 */
class ShowAdminOrderReviewRequest extends FormRequest
{
    /**
     * Разрешает любой запрос.
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
     * Правила валидации scope просмотра заказа.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(['address', 'composition'])],
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Параметр запроса scope обязателен.',
            'scope.in' => 'Некорректный scope. Используйте address или composition.',
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
            'scope' => 'область проверки',
        ];
    }

    /**
     * Scope проверки: address или composition.
     */
    public function scope(): string
    {
        return (string) $this->validated('scope');
    }
}
