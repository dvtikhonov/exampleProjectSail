<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query restaurant_id для каталога PhotoText.
 */
class PhotoTextCatalogRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
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
     * Правила: активный ресторан обязателен.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => $this->restaurantIdRules(),
        ];
    }

    /**
     * Идентификатор активного ресторана из query.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    /**
     * restaurant_id: обязателен, существует, активен, не soft-deleted.
     *
     * @return list<mixed>
     */
    private function restaurantIdRules(): array
    {
        return [
            'required',
            'integer',
            'min:1',
            Rule::exists('max_restaurants', 'id')
                ->where('is_active', true)
                ->whereNull('deleted_at'),
        ];
    }
}
