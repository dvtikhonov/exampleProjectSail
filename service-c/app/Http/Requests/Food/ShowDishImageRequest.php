<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация route-параметра {dish} для публичной отдачи изображения блюда.
 *
 * Без exists: — soft-deleted блюда остаются доступны для истории заказов.
 * Существование и наличие файла — в сервисе доставки изображения.
 */
class ShowDishImageRequest extends FormRequest
{
    /**
     * Разрешает любой запрос (публичный same-origin URL для img).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Ошибки валидации отдаём JSON (API-эндпоинт).
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
