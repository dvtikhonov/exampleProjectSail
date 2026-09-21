<?php

declare(strict_types=1);

namespace App\Http\Requests\Note;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация частичного обновления заметки.
 *
 * Те же поля, что при создании; все с префиксом sometimes.
 */
class UpdateNoteRequest extends FormRequest
{
    /** Публичный CRUD: обновление доступно без аутентификации. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации полей обновления заметки.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
