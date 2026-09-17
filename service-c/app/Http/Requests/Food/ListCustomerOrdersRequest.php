<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query-параметров списка заказов клиента.
 */
class ListCustomerOrdersRequest extends FormRequest
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    private const DEFAULT_PAGE = 1;

    private const MAX_PAGE = 10000;

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
     * Правила валидации пагинации списка заказов клиента.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
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
            'page' => 'номер страницы',
            'per_page' => 'размер страницы',
        ];
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
     * Размер страницы списка заказов (не более 50).
     */
    public function perPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : self::DEFAULT_PER_PAGE;
    }
}
