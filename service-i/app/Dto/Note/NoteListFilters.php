<?php

declare(strict_types=1);

namespace App\Dto\Note;

use App\Enums\NoteArchivedFilter;

/**
 * Нормализованные фильтры списка заметок (после IndexNoteRequest::filters()).
 *
 * Defaults задаются только в IndexNoteRequest::filters(), не здесь.
 */
final readonly class NoteListFilters
{
    /**
     * @param  list<string>  $tags
     */
    public function __construct(
        public ?string $q,
        public array $tags,
        public NoteArchivedFilter $archived,
        public string $sort,
        public int $limit,
        public int $offset,
    ) {}
}
