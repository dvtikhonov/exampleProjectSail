<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

/**
 * Валидация query restaurant_id для каталога PhotoText.
 */
class PhotoTextCatalogRequest extends PhotoTextAgentFormRequest
{
    /**
     * Правила: активный ресторан обязателен.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'restaurant_id' => $this->activeRestaurantIdRules(),
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
        ];
    }
}
