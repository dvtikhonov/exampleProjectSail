<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса отклонения заказа администратором.
 */
class RejectOrderReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'comment.required' => 'Укажите причину отклонения.',
            'comment.max' => 'Комментарий не должен превышать 1000 символов.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'comment' => 'причина отклонения',
        ];
    }

    /**
     * Возвращает нормализованный комментарий отклонения.
     */
    public function comment(): string
    {
        return trim((string) $this->validated('comment'));
    }
}
