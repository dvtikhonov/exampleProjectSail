<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query для GET admin/briskly-sync/vps-categories.
 *
 * Проверяется только форма restaurant_id; активность/наличие — через порт
 * (local DB или remote PhotoText), без Rule::exists по локальным таблицам.
 */
class ListBrisklySyncVpsCategoriesRequest extends FormRequest
{
    /**
     * Разрешает запрос (доступ роли — middleware food.order.admin:max_manager).
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
     * Правила валидации query.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => ['required', 'integer', 'min:1'],
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
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.integer' => 'Идентификатор ресторана должен быть числом.',
            'restaurant_id.min' => 'Идентификатор ресторана должен быть не меньше 1.',
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
     * Идентификатор ресторана из query.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }
}
