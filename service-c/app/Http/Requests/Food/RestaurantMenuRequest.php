<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query-параметров меню ресторана.
 */
class RestaurantMenuRequest extends FormRequest
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
     * Правила валидации флага недоступных блюд.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'include_unavailable' => ['nullable', 'boolean'],
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
            'include_unavailable' => 'показывать недоступные блюда',
        ];
    }

    /**
     * Запрошен ли показ недоступных блюд.
     */
    public function includeUnavailable(): bool
    {
        return $this->boolean('include_unavailable');
    }
}
