<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация действий с ручным заказом в статусе «Черновик после сканирования».
 *
 * Тело запроса не требуется; проверяется только route-параметр {order}.
 * Бизнес-ограничения (is_manual, статус) — в сервисе.
 */
class DraftAfterScanningOrderActionRequest extends FormRequest
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
     * Подмешивает {order} из маршрута в данные для валидации.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'order' => $this->route('order'),
        ]);
    }

    /**
     * Правила валидации идентификатора заказа.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'order' => ['required', 'integer', 'min:1'],
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
            'order' => 'заказ',
        ];
    }

    /**
     * ID заказа из маршрута.
     */
    public function orderId(): int
    {
        return (int) $this->validated('order');
    }
}
