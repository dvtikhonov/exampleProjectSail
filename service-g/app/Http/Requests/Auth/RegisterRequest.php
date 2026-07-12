<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\DTO\Auth\RegisterUserDto;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Валидация запроса регистрации и преобразование в RegisterUserDto.
 */
class RegisterRequest extends FormRequest
{
    /** Регистрация доступна гостям. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации полей регистрации.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /** Собирает DTO из провалидированных полей запроса. */
    public function toDto(): RegisterUserDto
    {
        return new RegisterUserDto(
            name: (string) $this->validated('name'),
            email: (string) $this->validated('email'),
            password: (string) $this->validated('password'),
        );
    }
}
