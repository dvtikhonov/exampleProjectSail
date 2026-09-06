<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация подтверждения шага проверки заказа.
 *
 * Тело запроса не требуется; бизнес-ограничения — в сервисе.
 */
class ApproveOrderReviewRequest extends FormRequest
{
    /**
     * Разрешает запрос (доступ роли — middleware food.order.admin).
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
     * Тело запроса пустое.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [];
    }
}
