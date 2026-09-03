<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Общие правила и accessor для активного ресторана в запросах PhotoText.
 *
 * @mixin FormRequest
 */
trait ValidatesActiveRestaurant
{
    /**
     * restaurant_id: обязателен, существует, активен, не soft-deleted.
     *
     * @return list<mixed>
     */
    protected function activeRestaurantIdRules(): array
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

    /**
     * Сообщения об ошибках валидации restaurant_id.
     *
     * @return array<string, string>
     */
    protected function activeRestaurantIdMessages(): array
    {
        return [
            'restaurant_id.required' => 'Укажите ресторан.',
            'restaurant_id.exists' => 'Ресторан не найден или неактивен.',
        ];
    }

    /**
     * Идентификатор активного ресторана из валидированных данных.
     */
    public function restaurantId(): int
    {
        return (int) $this->validated('restaurant_id');
    }
}
