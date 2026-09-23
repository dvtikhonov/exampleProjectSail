<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Фильтр по полю archived для списка заметок.
 *
 * API-значения: false | true | all (совпадают с value case).
 */
enum NoteArchivedFilter: string
{
    case Active = 'false';
    case Archived = 'true';
    case All = 'all';
}
