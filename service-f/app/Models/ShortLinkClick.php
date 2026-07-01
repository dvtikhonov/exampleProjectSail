<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShortLinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'short_link_id',
    'ip_address',
    'visited_at',
])]
/** Запись одного перехода по короткой ссылке (журнал кликов). */
class ShortLinkClick extends Model
{
    /** @use HasFactory<ShortLinkClickFactory> */
    use HasFactory;

    public $timestamps = false;

    /**
     * @return BelongsTo<ShortLink, $this>
     */
    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }
}
