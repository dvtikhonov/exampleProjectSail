<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query-параметров списка категорий меню для админки.
 */
class ListAdminMenuCategoriesRequest extends FormRequest
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
     * Правила валидации фильтра списка категорий.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['nullable', 'integer', 'min:1'],
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
            'restaurant_id' => 'ресторан',
        ];
    }

    /**
     * Фильтр по ресторану или null.
     */
    public function restaurantId(): ?int
    {
        $value = $this->validated('restaurant_id');

        return $value !== null ? (int) $value : null;
    }
}
