<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Requests;

use App\Modules\SchedulePhoto\Http\Rules\AllowedSchedulePhotoBaseUrl;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query GET /api/food/admin/schedule-photo/catalog.
 *
 * base_url — allow-list. restaurant_id — целое min:1 без Rule::exists:
 * идентификатор принадлежит выбранному серверу, не локальным таблицам.
 */
class ListSchedulePhotoCatalogRequest extends FormRequest
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
            'restaurant_id' => ['required', 'integer', 'min:1'],
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
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.integer' => 'Идентификатор ресторана должен быть числом.',
            'restaurant_id.min' => 'Идентификатор ресторана должен быть не меньше 1.',
        ];
    }

    /**
     * Нормализованный BASE_URL без завершающего слэша.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->validated('base_url'), '/');
    }

    /**
     * Идентификатор ресторана на выбранном origin.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }
}
