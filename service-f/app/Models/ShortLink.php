<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShortLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'original_url',
    'code',
    'clicks_count',
])]
/** Eloquent-модель короткой ссылки (original_url → code → clicks_count). */
class ShortLink extends Model
{
    /** @use HasFactory<ShortLinkFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ShortLinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(ShortLinkClick::class)->latest('visited_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicks_count' => 'integer',
        ];
    }
}
