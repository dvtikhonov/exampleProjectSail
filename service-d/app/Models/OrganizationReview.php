<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\OrganizationReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'external_review_id',
    'author_name',
    'published_at',
    'text',
    'rating',
    'sort_order',
])]
class OrganizationReview extends Model
{
    /** @use HasFactory<OrganizationReviewFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'rating' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
