<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Краткие метаданные организации для ответа со списком отзывов.
 *
 * @mixin Organization
 */
class OrganizationMetaResource extends JsonResource
{
    /**
     * Преобразует модель организации в сокращённый набор полей.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'sync_status' => $this->sync_status->value,
        ];
    }
}
