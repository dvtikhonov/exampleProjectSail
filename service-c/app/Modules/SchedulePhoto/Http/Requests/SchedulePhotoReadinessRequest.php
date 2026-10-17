<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Requests;

use App\Modules\SchedulePhoto\Http\Rules\AllowedSchedulePhotoBaseUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация POST /api/food/admin/schedule-photo/readiness.
 *
 * Поля: base_url (только allow-list origin, без произвольного SSRF).
 */
class SchedulePhotoReadinessRequest extends FormRequest
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
        return [
            'base_url' => ['required', 'string', 'url', 'max:500', new AllowedSchedulePhotoBaseUrl],
        ];
    }

    /**
     * Нормализованный BASE_URL без завершающего слэша.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->validated('base_url'), '/');
    }
}
