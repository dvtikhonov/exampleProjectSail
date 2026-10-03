<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query для GET admin/briskly-sync/source-lines.
 *
 * Проверяются: restaurant_id (активный), vps_category_id (принадлежит ресторану),
 * search_text (nullable, max 120).
 */
class ListBrisklySyncSourceLinesRequest extends FormRequest
{
    /**
     * Разрешает запрос (доступ роли — middleware food.order.admin:max_manager).
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
     * Правила валидации фильтров source-lines.
     *
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
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.exists' => 'Ресторан не найден или неактивен.',
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
     * Идентификатор активного ресторана.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
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
