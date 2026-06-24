<?php

declare(strict_types=1);

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация запроса отправки сообщения в чат заказа.
 */
class SendOrderMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required' => 'Введите текст сообщения.',
            'body.max' => 'Сообщение не должно превышать 2000 символов.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'сообщение',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = $this->input('body');

            if (! is_string($body) || trim($body) === '') {
                $validator->errors()->add('body', 'Введите текст сообщения.');
            }
        });
    }

    public function body(): string
    {
        return trim((string) $this->validated('body'));
    }
}
