<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация route-параметра {dish} для show/destroy админ-CRUD блюд.
 *
 * Тело запроса не требуется; существование и бизнес-ограничения — в сервисе.
 */
class ShowAdminDishRequest extends FormRequest
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
     * Подмешивает {dish} из маршрута в данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'dish' => $this->route('dish'),
        ]);
    }

    /**
     * Правила валидации идентификатора блюда.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'dish' => ['required', 'integer', 'min:1'],
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
            'dish' => 'блюдо',
        ];
    }

    /**
     * ID блюда из маршрута.
     */
    public function dishId(): int
    {
        return (int) $this->validated('dish');
    }
}
