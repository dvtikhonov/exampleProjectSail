<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса ролей текущего пользователя mini-app.
 *
 * Тело запроса не требуется; эндпоинт доступен любому max.miniapp.auth
 * и отдаёт список активных ролей (возможно пустой).
 */
class AdminReviewMeRequest extends FormRequest
{
    /**
     * Разрешает запрос (роль не требуется — достаточно max.miniapp.auth).
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
