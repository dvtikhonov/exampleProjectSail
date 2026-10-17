<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация POST /api/food/admin/schedule-photo/runs/{id}/apply.
 *
 * Тело пустое: подтверждение — сам факт POST после human-confirm в UI.
 */
class ApplySchedulePhotoRunRequest extends FormRequest
{
    /**
     * Разрешает запрос (роль — middleware food.order.admin:menu_manager).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
