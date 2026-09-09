<?php

declare(strict_types=1);

namespace App\Http\Requests\Food\PhotoText;

use App\Http\Requests\Food\PhotoText\Concerns\ValidatesActiveRestaurant;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Базовый FormRequest агента PhotoText: JSON-ответы и валидация активного ресторана.
 */
abstract class PhotoTextAgentFormRequest extends FormRequest
{
    use ValidatesActiveRestaurant;

    /**
     * Разрешает любой запрос (доступ проверяет middleware агента).
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
}
