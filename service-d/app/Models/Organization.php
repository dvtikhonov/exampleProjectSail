<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationSyncStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'source_url',
    'canonical_url',
    'yandex_org_id',
    'name',
    'address',
    'average_rating',
    'ratings_count',
    'reviews_count',
    'sync_status',
    'sync_error',
    'last_synced_at',
])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<OrganizationReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(OrganizationReview::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'average_rating' => 'decimal:2',
            'ratings_count' => 'integer',
            'reviews_count' => 'integer',
            'sync_status' => OrganizationSyncStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }
}
