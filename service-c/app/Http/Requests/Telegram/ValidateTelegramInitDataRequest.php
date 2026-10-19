<?php

declare(strict_types=1);

namespace App\Http\Requests\Telegram;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса аутентификации Telegram Mini App по initData.
 *
 * Валидируемые поля:
 * - init_data: обязательная непустая строка подписанных данных Telegram.WebApp.initData.
 */
class ValidateTelegramInitDataRequest extends FormRequest
{
    /**
     * Разрешает выполнение запроса.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации строки initData.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'init_data' => ['required', 'string', 'min:1'],
        ];
    }

    /**
     * Возвращает строку initData из валидированных данных.
     */
    public function initData(): string
    {
        return (string) $this->validated('init_data');
    }
}
