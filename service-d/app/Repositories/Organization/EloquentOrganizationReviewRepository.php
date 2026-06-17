<?php

declare(strict_types=1);

namespace App\Repositories\Organization;

use App\Contracts\OrganizationReviewRepositoryInterface;
use App\DTO\YandexMaps\ParsedReviewDto;
use App\Models\OrganizationReview;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EloquentOrganizationReviewRepository implements OrganizationReviewRepositoryInterface
{
    public function replaceForOrganization(int $organizationId, array $reviews): void
    {
        OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->delete();

        $rows = [];

        foreach ($reviews as $index => $review) {
            if (! $review instanceof ParsedReviewDto || $review->externalId === '') {
                continue;
            }

            $rows[] = [
                'organization_id' => $organizationId,
                'external_review_id' => $review->externalId,
                'author_name' => $review->authorName !== '' ? $review->authorName : 'Аноним',
                'published_at' => ($review->publishedAt ?? Carbon::now())->toDateTimeString(),
                'text' => $review->text,
                'rating' => max(0, min(5, $review->rating ?? 0)),
                'sort_order' => $index,
                'created_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ];
        }

        if ($rows === []) {
            return;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            OrganizationReview::query()->insert($chunk);
        }
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 50): LengthAwarePaginator
    {
        return OrganizationReview::query()
            ->where('organization_id', $organizationId)
            ->orderBy('sort_order')
            ->paginate($perPage);
    }
}
