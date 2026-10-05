<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use Illuminate\Validation\Rule;

/**
 * Валидация query для GET phototext/briskly-source-lines.
 *
 * Проверяются: restaurant_id (активный), vps_category_id (принадлежит ресторану),
 * search_text (nullable, max 120).
 */
class PhotoTextBrisklySourceLinesRequest extends PhotoTextAgentFormRequest
{
    /**
     * Правила валидации фильтров source-lines.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $restaurantId = (int) $this->input('restaurant_id');

        return [
            'restaurant_id' => $this->activeRestaurantIdRules(),
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
        ];
    }

    /**
     * Сообщения об ошибках валидации.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->activeRestaurantIdMessages(),
            'vps_category_id.exists' => 'Категория меню не найдена для выбранного ресторана.',
            'search_text.max' => 'Текст поиска не должен превышать 120 символов.',
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
            'restaurant_id' => 'ресторан',
            'vps_category_id' => 'категория VPS',
            'search_text' => 'текст поиска',
        ];
    }

    /**
     * Опциональный фильтр категории меню ресторана.
     */
    public function vpsCategoryId(): ?int
    {
        $value = $this->validated('vps_category_id');

        return $value === null ? null : (int) $value;
    }

    /**
     * Опциональный текст поиска по display_name (после trim; пустая строка → null).
     */
    public function searchText(): ?string
    {
        $value = $this->validated('search_text');

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
