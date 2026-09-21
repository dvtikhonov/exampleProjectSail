<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Note;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Бизнес-логика CRUD заметок (Eloquent напрямую, без Repository).
 */
class NoteService
{
    /**
     * Список заметок без фильтров, сортировки и пагинации.
     *
     * @return Collection<int, Note>
     */
    public function list(): Collection
    {
        // TODO: filters, sort, pagination

        return Note::query()->get();
    }

    /**
     * Создаёт заметку из провалидированных данных.
     *
     * @param  array{title: string, content?: string|null, tags?: array<int, string>|null, archived?: bool}  $data
     */
    public function create(array $data): Note
    {
        return Note::query()->create($data);
    }

    /**
     * Находит заметку по идентификатору.
     *
     * @throws ModelNotFoundException
     */
    public function find(int $id): Note
    {
        return Note::query()->findOrFail($id);
    }

    /**
     * Частично обновляет заметку.
     *
     * @param  array{title?: string, content?: string|null, tags?: array<int, string>|null, archived?: bool}  $data
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data): Note
    {
        $note = $this->find($id);
        $note->fill($data);
        $note->save();

        return $note->refresh();
    }

    /**
     * Удаляет заметку.
     *
     * @throws ModelNotFoundException
     */
    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }
}
