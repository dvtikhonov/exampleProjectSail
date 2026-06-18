<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\Organization\InvalidOrganizationCandidateException;
use App\Exceptions\Organization\OrganizationNotFoundException;
use App\Exceptions\Organization\OrganizationResolveSessionExpiredException;
use App\Exceptions\YandexMaps\YandexMapsParserException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ConfirmOrganizationRequest;
use App\Http\Requests\Organization\OrganizationIdRequest;
use App\Http\Requests\Organization\ResolveOrganizationRequest;
use App\Http\Requests\Organization\ShowOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationReview;
use App\Models\User;
use App\Services\YandexMaps\OrganizationConfirmService;
use App\Services\YandexMaps\OrganizationResolveService;
use App\Services\YandexMaps\OrganizationResyncService;
use App\Services\YandexMaps\OrganizationReviewQueryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationResolveService $resolveService,
        private readonly OrganizationConfirmService $confirmService,
        private readonly OrganizationResyncService $resyncService,
        private readonly OrganizationReviewQueryService $reviewQueryService,
    ) {}

    public function show(ShowOrganizationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $organizationId = $request->organizationId();

        try {
            $organization = $this->reviewQueryService->findOrganizationForUser($user, $organizationId);
        } catch (OrganizationNotFoundException) {
            return response()->json([
                'organization' => null,
            ]);
        }

        return response()->json([
            'organization' => $this->serializeOrganization($organization),
        ]);
    }

    public function resolve(ResolveOrganizationRequest $request): JsonResponse
    {
        try {
            $result = $this->resolveService->resolve($request->toDto());
        } catch (YandexMapsParserException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json($result->toArray());
    }

    public function confirm(ConfirmOrganizationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $organization = $this->confirmService->confirm($user, $request->toDto());
        } catch (OrganizationResolveSessionExpiredException|InvalidOrganizationCandidateException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'organization' => $this->serializeOrganization($organization),
        ], 202);
    }

    public function syncStatus(OrganizationIdRequest $request): JsonResponse
    {
        $organization = $this->reviewQueryService->findOrganizationById($request->organizationId());

        return response()->json([
            'sync_status' => $organization->sync_status->value,
            'sync_error' => $organization->sync_error,
            'last_synced_at' => $organization->last_synced_at?->toIso8601String(),
        ]);
    }

    public function resync(OrganizationIdRequest $request): JsonResponse
    {
        try {
            $organization = $this->resyncService->resync($request->organizationId());
        } catch (OrganizationNotFoundException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 404);
        }

        return response()->json([
            'organization' => $this->serializeOrganization($organization),
        ], 202);
    }

    public function reviews(OrganizationIdRequest $request): JsonResponse
    {
        $organization = $this->reviewQueryService->findOrganizationById($request->organizationId());
        $reviews = $this->reviewQueryService->paginatedReviews($organization);
        $isRefreshing = $this->reviewQueryService->isRefreshingCachedReviews($organization);

        return response()->json([
            'organization' => $this->serializeOrganizationMeta($organization),
            'reviews' => $this->serializeReviewsPaginator($reviews),
            'is_refreshing' => $isRefreshing,
            'warning' => $isRefreshing ? OrganizationReviewQueryService::REFRESHING_WARNING : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrganization(Organization $organization): array
    {
        return [
            'id' => $organization->id,
            'source_url' => $organization->source_url,
            'canonical_url' => $organization->canonical_url,
            'yandex_org_id' => $organization->yandex_org_id,
            'name' => $organization->name,
            'address' => $organization->address,
            'average_rating' => $organization->average_rating !== null ? (float) $organization->average_rating : null,
            'ratings_count' => $organization->ratings_count,
            'reviews_count' => $organization->reviews_count,
            'sync_status' => $organization->sync_status->value,
            'sync_error' => $organization->sync_error,
            'last_synced_at' => $organization->last_synced_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrganizationMeta(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'address' => $organization->address,
            'average_rating' => $organization->average_rating !== null ? (float) $organization->average_rating : null,
            'ratings_count' => $organization->ratings_count,
            'reviews_count' => $organization->reviews_count,
            'sync_status' => $organization->sync_status->value,
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, OrganizationReview>  $paginator
     * @return array<string, mixed>
     */
    private function serializeReviewsPaginator(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()
                ->map(fn (OrganizationReview $review): array => [
                    'id' => $review->id,
                    'author_name' => $review->author_name,
                    'published_at' => $review->published_at->toIso8601String(),
                    'text' => $review->text,
                    'rating' => $review->rating,
                ])
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
