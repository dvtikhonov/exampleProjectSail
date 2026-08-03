<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров списка заказов для админ-проверки.
 */
class ListAdminOrdersRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

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
     * Правила валидации фильтров списка заказов проверки.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', Rule::in(['address', 'composition'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
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
            'status.in' => 'Некорректный status. Используйте pending или all.',
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
            'status' => 'статус',
            'per_page' => 'размер страницы',
        ];
    }

    /**
     * Scope проверки: address или composition.
     */
    public function scope(): string
    {
        return (string) $this->validated('scope');
    }

    /**
     * Статус фильтра списка (по умолчанию pending).
     */
    public function listStatus(): string
    {
        $value = $this->validated('status');

        return is_string($value) && $value !== '' ? $value : 'pending';
    }

    /**
     * Размер страницы списка заказов.
     */
    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}
