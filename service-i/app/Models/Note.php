<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Заметка (Note).
 *
 * @property int $id
 * @property string $title
 * @property string|null $content
 * @property array<int, string>|null $tags
 * @property bool $archived
 * @property array<string, mixed>|null $meta
 */
#[Fillable(['title', 'content', 'tags', 'archived', 'meta'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * Приведение атрибутов модели к нужным типам.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'meta' => 'array',
            'archived' => 'boolean',
        ];
    }
}
