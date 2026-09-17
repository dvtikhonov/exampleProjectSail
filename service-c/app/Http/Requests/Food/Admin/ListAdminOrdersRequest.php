<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use App\Enums\Food\Order\AdminOrderListScope;
use App\Enums\Food\Order\AdminOrderListStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров списка заказов для админ-проверки.
 */
class ListAdminOrdersRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    private const DEFAULT_PAGE = 1;

    private const MAX_PAGE = 10000;

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
            'scope' => ['required', 'string', Rule::enum(AdminOrderListScope::class)],
            'status' => ['nullable', 'string', Rule::enum(AdminOrderListStatus::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
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
            'scope.Illuminate\Validation\Rules\Enum' => 'Некорректный scope. Используйте address или composition.',
            'status.Illuminate\Validation\Rules\Enum' => 'Некорректный status. Используйте pending или all.',
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
            'page' => 'номер страницы',
            'per_page' => 'размер страницы',
        ];
    }

    /**
     * Scope проверки: address или composition.
     */
    public function scope(): AdminOrderListScope
    {
        return AdminOrderListScope::from((string) $this->validated('scope'));
    }

    /**
     * Статус фильтра списка (по умолчанию pending).
     */
    public function listStatus(): AdminOrderListStatus
    {
        $value = $this->validated('status');

        if (! is_string($value) || $value === '') {
            return AdminOrderListStatus::Pending;
        }

        return AdminOrderListStatus::from($value);
    }

    /**
     * Номер страницы списка заказов.
     */
    public function page(): int
    {
        $value = $this->validated('page');

        return $value !== null ? (int) $value : self::DEFAULT_PAGE;
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
