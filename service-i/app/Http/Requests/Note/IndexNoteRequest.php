<?php

declare(strict_types=1);

namespace App\Http\Requests\Note;

use App\Dto\Note\NoteListFilters;
use App\Enums\NoteArchivedFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Валидация query-параметров GET /api/notes.
 *
 * Поля: q, tags, archived, sort, limit, offset.
 * Defaults — только в filters().
 */
class IndexNoteRequest extends FormRequest
{
    /** Публичный CRUD: список доступен без аутентификации. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Нормализация q (trim) и tags (CSV или array → string[]) до валидации.
     */
    protected function prepareForValidation(): void
    {
        $q = $this->input('q');
        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }

        $tags = $this->input('tags');
        if (is_string($tags)) {
            $parsed = array_values(array_filter(
                array_map('trim', explode(',', $tags)),
                static fn (string $t): bool => $t !== ''
            ));
            $this->merge(['tags' => $parsed]);
        } elseif (is_array($tags)) {
            $parsed = array_values(array_filter(
                array_map('trim', $tags),
                static fn (string $t): bool => $t !== ''
            ));
            $this->merge(['tags' => $parsed]);
        }
    }

    /**
     * Правила валидации query-параметров списка.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:50'],
            'archived' => ['nullable', Rule::enum(NoteArchivedFilter::class)],
            'sort' => [
                'nullable',
                'string',
                'in:created_at,-created_at,updated_at,-updated_at,title,-title',
            ],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Единственное место defaults для списка заметок.
     *
     * Query-параметры приходят строками — явный cast limit/offset в int.
     */
    public function filters(): NoteListFilters
    {
        $q = $this->validated('q');
        if (! is_string($q) || $q === '') {
            $q = null;
        }

        $tags = $this->validated('tags') ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }

        $archivedRaw = $this->validated('archived');
        $archived = match (true) {
            $archivedRaw instanceof NoteArchivedFilter => $archivedRaw,
            is_string($archivedRaw) => NoteArchivedFilter::from($archivedRaw),
            default => NoteArchivedFilter::Active,
        };

        $sort = $this->validated('sort');
        if (! is_string($sort) || $sort === '') {
            $sort = '-updated_at';
        }

        return new NoteListFilters(
            q: $q,
            tags: array_values($tags),
            archived: $archived,
            sort: $sort,
            limit: (int) ($this->validated('limit') ?? 20),
            offset: (int) ($this->validated('offset') ?? 0),
        );
    }
}
