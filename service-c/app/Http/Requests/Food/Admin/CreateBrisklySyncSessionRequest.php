<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация POST admin/briskly-sync/sessions.
 *
 * Поля: restaurant_id, vps_category_id, search_text, clarification.
 * Bearer Briskly захватывается на сервере (не из тела запроса).
 */
class CreateBrisklySyncSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function wantsJson(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $restaurantId = (int) $this->input('restaurant_id');

        return [
            'restaurant_id' => [
                'required',
                'integer',
                'min:1',
                Rule::exists('max_restaurants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'vps_category_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('max_menu_categories', 'id')
                    ->where(static function ($query) use ($restaurantId): void {
                        $query->whereNull('deleted_at');
                        if ($restaurantId > 0) {
                            $query->where('restaurant_id', $restaurantId);
                        }
                    }),
            ],
            'search_text' => ['nullable', 'string', 'max:120'],
            'clarification' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.exists' => 'Ресторан не найден или неактивен.',
            'vps_category_id.exists' => 'Категория меню не найдена для выбранного ресторана.',
            'search_text.max' => 'Текст поиска не должен превышать 120 символов.',
            'clarification.max' => 'Уточнение не должно превышать 2000 символов.',
        ];
    }

    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }

    public function vpsCategoryId(): ?int
    {
        $value = $this->validated('vps_category_id');

        return $value === null ? null : (int) $value;
    }

    public function searchText(): ?string
    {
        $value = $this->validated('search_text');
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    public function clarification(): ?string
    {
        $value = $this->validated('clarification');
        if (! is_string($value)) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
