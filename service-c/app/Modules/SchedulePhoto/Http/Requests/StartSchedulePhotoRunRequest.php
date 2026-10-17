<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Requests;

use App\Modules\SchedulePhoto\Http\Rules\AllowedSchedulePhotoBaseUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Валидация POST /api/food/admin/schedule-photo/runs (FormData).
 *
 * Поля: base_url (allow-list), date_from, restaurant_id, category_ids[], photo.
 */
class StartSchedulePhotoRunRequest extends FormRequest
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
            'date_from' => ['required', 'date_format:Y-m-d'],
            'restaurant_id' => ['required', 'integer', 'min:1'],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'min:1', 'distinct'],
            'photo' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:10240',
                Rule::dimensions()->maxWidth(8000)->maxHeight(8000),
            ],
        ];
    }

    /**
     * Нормализованный BASE_URL.
     */
    public function baseUrl(): string
    {
        return rtrim((string) $this->validated('base_url'), '/');
    }

    /**
     * Дата начала окна (7 дней считает сервис).
     */
    public function dateFrom(): string
    {
        return (string) $this->validated('date_from');
    }

    /**
     * ID ресторана.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->validated('category_ids');

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $ids));
    }

    /**
     * Загруженное фото графика.
     */
    public function photo(): UploadedFile
    {
        /** @var UploadedFile $photo */
        $photo = $this->file('photo');

        return $photo;
    }
}
