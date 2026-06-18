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
use App\Http\Resources\OrganizationMetaResource;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\OrganizationReviewCollection;
use App\Models\User;
use App\Services\YandexMaps\OrganizationConfirmService;
use App\Services\YandexMaps\OrganizationResolveService;
use App\Services\YandexMaps\OrganizationResyncService;
use App\Services\YandexMaps\OrganizationReviewQueryService;
use App\Services\YandexMaps\ResolveOrganizationInputFactory;
use Illuminate\Http\JsonResponse;

/**
 * API организаций Яндекс.Карт: разрешение по URL, подтверждение кандидата,
 * статус синхронизации и выдача отзывов.
 */
class OrganizationController extends Controller
{
    public function __construct(
        private readonly OrganizationResolveService $resolveService,
        private readonly ResolveOrganizationInputFactory $resolveInputFactory,
        private readonly OrganizationConfirmService $confirmService,
        private readonly OrganizationResyncService $resyncService,
        private readonly OrganizationReviewQueryService $reviewQueryService,
    ) {}

    /**
     * Карточка организации текущего пользователя.
     * Если organization_id не передан или организация не найдена — organization: null.
     */
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
            'organization' => OrganizationResource::make($organization),
        ]);
    }

    /**
     * Разрешает ссылку или поисковый запрос в список кандидатов через yandex-parser.
     * Промежуточный результат сохраняется в resolve-сессии (кеш).
     */
    public function resolve(ResolveOrganizationRequest $request): JsonResponse
    {
        try {
            $result = $this->resolveService->resolve(
                $this->resolveInputFactory->fromUrl((string) $request->validated('url')),
            );
        } catch (YandexMapsParserException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }

        return response()->json($result->toArray());
    }

    /**
     * Подтверждает выбранного кандидата из resolve-сессии и запускает синхронизацию.
     */
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
            'organization' => OrganizationResource::make($organization),
        ], 202);
    }

    /**
     * Возвращает текущий статус фоновой синхронизации организации.
     */
    public function syncStatus(OrganizationIdRequest $request): JsonResponse
    {
        $organization = $this->reviewQueryService->findOrganizationById($request->organizationId());

        return response()->json([
            'sync_status' => $organization->sync_status->value,
            'sync_error' => $organization->sync_error,
            'last_synced_at' => $organization->last_synced_at?->toIso8601String(),
        ]);
    }

    /**
     * Повторно ставит организацию в очередь на синхронизацию с Яндекс.Картами.
     */
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
            'organization' => OrganizationResource::make($organization),
        ], 202);
    }

    /**
     * Пагинированный список отзывов организации с метаданными и флагом обновления кеша.
     */
    public function reviews(OrganizationIdRequest $request): JsonResponse
    {
        $organization = $this->reviewQueryService->findOrganizationById($request->organizationId());
        $reviews = $this->reviewQueryService->paginatedReviews($organization);
        $isRefreshing = $this->reviewQueryService->isRefreshingCachedReviews($organization);

        return response()->json([
            'organization' => OrganizationMetaResource::make($organization),
            'reviews' => new OrganizationReviewCollection($reviews),
            'is_refreshing' => $isRefreshing,
            'warning' => $isRefreshing ? OrganizationReviewQueryService::REFRESHING_WARNING : null,
        ]);
    }
}
