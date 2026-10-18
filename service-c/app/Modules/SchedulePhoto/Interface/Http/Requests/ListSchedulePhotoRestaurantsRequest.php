<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Requests;

use App\Modules\SchedulePhoto\Interface\Http\Rules\AllowedSchedulePhotoBaseUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query GET /api/food/admin/schedule-photo/restaurants.
 *
 * base_url — только allow-list. Рестораны принадлежат выбранному origin, не локальной БД.
 */
class ListSchedulePhotoRestaurantsRequest extends FormRequest
{
    /**
     * Разрешает запрос (роль — middleware food.order.admin:menu_manager).
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'base_url' => ['required', 'string', 'url', 'max:500', new AllowedSchedulePhotoBaseUrl],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'base_url.required' => 'Укажите BASE_URL.',
            'base_url.url' => 'BASE_URL должен быть адресом.',
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
