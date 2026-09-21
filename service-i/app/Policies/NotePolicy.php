<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Заглушка политики заметок (allow-all) «на будущее».
 *
 * Контроллер authorize не вызывает — публичный CRUD.
 */
class NotePolicy
{
    /** Просмотр списка: разрешено всем. */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /** Просмотр заметки: разрешено всем. */
    public function view(?User $user, Note $note): bool
    {
        return true;
    }

    /** Создание: разрешено всем. */
    public function create(?User $user): bool
    {
        return true;
    }

    /** Обновление: разрешено всем. */
    public function update(?User $user, Note $note): bool
    {
        return true;
    }

    /** Удаление: разрешено всем. */
    public function delete(?User $user, Note $note): bool
    {
        return true;
    }
}
