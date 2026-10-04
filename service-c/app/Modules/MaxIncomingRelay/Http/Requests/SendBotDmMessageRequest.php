<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Валидация тела запроса отправки сообщения в Bot DM.
 */
class SendBotDmMessageRequest extends FormRequest
{
    /**
     * Разрешает отправку (роль — middleware food.order.admin:max_manager).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации текста сообщения.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Сообщения об ошибках валидации текста.
     *
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
     * Человекочитаемые имена атрибутов.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => 'сообщение',
        ];
    }

    /**
     * Проверяет, что текст сообщения не состоит только из пробелов.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = $this->input('body');

            if (! is_string($body) || trim($body) === '') {
                $validator->errors()->add('body', 'Введите текст сообщения.');
            }
        });
    }

    /**
     * Возвращает нормализованный текст сообщения.
     */
    public function body(): string
    {
        return trim((string) $this->validated('body'));
    }
}
