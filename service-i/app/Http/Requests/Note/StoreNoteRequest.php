<?php

declare(strict_types=1);

namespace App\Http\Requests\Note;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация создания заметки.
 *
 * Поля: title (required), content, tags, tags.*, archived.
 */
class StoreNoteRequest extends FormRequest
{
    /** Публичный CRUD: создание доступно без аутентификации. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Правила валидации полей создания заметки.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'archived' => ['sometimes', 'boolean'],
        ];
    }
}
