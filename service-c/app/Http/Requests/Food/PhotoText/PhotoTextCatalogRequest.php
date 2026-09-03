<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use App\Http\Requests\Food\PhotoText\Concerns\ValidatesActiveRestaurant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация query restaurant_id для каталога PhotoText.
 */
class PhotoTextCatalogRequest extends FormRequest
{
    use ValidatesActiveRestaurant;

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
            'restaurant_id' => $this->activeRestaurantIdRules(),
        ];
    }
}
