<?php

namespace App\Http\Requests\Auth;

use App\DTO\Auth\LoginUserDto;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса входа и преобразование в LoginUserDto.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Собирает DTO из провалидированных полей запроса.
     */
    public function toDto(): LoginUserDto
    {
        return new LoginUserDto(
            email: (string) $this->validated('email'),
            password: (string) $this->validated('password'),
            remember: $this->boolean('remember'),
        );
    }
}
