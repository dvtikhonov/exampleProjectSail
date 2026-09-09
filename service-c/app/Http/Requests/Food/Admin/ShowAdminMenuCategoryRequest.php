<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация route-параметра {menuCategory} для show/destroy админ-CRUD категорий.
 *
 * Тело запроса не требуется; существование и бизнес-ограничения — в сервисе.
 */
class ShowAdminMenuCategoryRequest extends FormRequest
{
    /**
     * Разрешает запрос (доступ роли — middleware food.order.admin:menu_manager).
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
     * Подмешивает {menuCategory} из маршрута в данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'menuCategory' => $this->route('menuCategory'),
        ]);
    }

    /**
     * Правила валидации идентификатора категории меню.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'menuCategory' => ['required', 'integer', 'min:1'],
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
            'menuCategory' => 'категория меню',
        ];
    }

    /**
     * ID категории меню из маршрута.
     */
    public function menuCategoryId(): int
    {
        return (int) $this->validated('menuCategory');
    }
}
