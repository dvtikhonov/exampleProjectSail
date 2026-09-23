<?php

declare(strict_types=1);

namespace App\Services;

use App\Dto\Note\NoteListFilters;
use App\Enums\NoteArchivedFilter;
use App\Models\Note;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

/**
 * Бизнес-логика CRUD заметок (Eloquent напрямую, без Repository).
 */
class NoteService
{
    /**
     * Список заметок: фильтры → total → sort + offset/limit.
     *
     * @return array{items: Collection<int, Note>, total: int}
     */
    public function list(NoteListFilters $filters): array
    {
        $query = Note::query();

        if ($filters->q !== null) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'like', "%{$filters->q}%")
                    ->orWhere('content', 'like', "%{$filters->q}%");
            });
        }
//dump($filters);
        foreach ($filters->tags as $tag) {
            $query->whereJsonContains('tags', $tag);
        }

        if ($filters->archived !== NoteArchivedFilter::All) {
            $query->where(
                'archived',
                $filters->archived === NoteArchivedFilter::Archived
            );
        }

        $total = (clone $query)->count();

        $sort = $filters->sort;
        $desc = str_starts_with($sort, '-');
        $column = ltrim($sort, '-');

        $items = (clone $query)
            ->orderBy($column, $desc ? 'desc' : 'asc')
            ->offset($filters->offset)
            ->limit($filters->limit)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
        ];
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
