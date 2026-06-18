<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrganizationReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Один отзыв организации в формате API.
 *
 * @mixin OrganizationReview
 */
class OrganizationReviewResource extends JsonResource
{
    /**
     * Преобразует модель отзыва в массив полей API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name,
            'published_at' => $this->published_at->toIso8601String(),
            'text' => $this->text,
            'rating' => $this->rating,
        ];
    }
}
