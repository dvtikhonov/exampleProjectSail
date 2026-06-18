<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Полная карточка организации для API-ответов.
 *
 * @mixin Organization
 */
class OrganizationResource extends JsonResource
{
    /**
     * Преобразует модель организации в массив полей API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_url' => $this->source_url,
            'canonical_url' => $this->canonical_url,
            'yandex_org_id' => $this->yandex_org_id,
            'name' => $this->name,
            'address' => $this->address,
            'average_rating' => $this->average_rating !== null ? (float) $this->average_rating : null,
            'ratings_count' => $this->ratings_count,
            'reviews_count' => $this->reviews_count,
            'sync_status' => $this->sync_status->value,
            'sync_error' => $this->sync_error,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
