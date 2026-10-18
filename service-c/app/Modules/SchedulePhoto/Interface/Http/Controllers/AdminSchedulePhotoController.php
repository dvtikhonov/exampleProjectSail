<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Interface\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminRunInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoAdminViewBuilderInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoDiscardedResolverInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoRunApplyInterface;
use App\Modules\SchedulePhoto\Application\Contract\SchedulePhotoUiOriginConfigBuilderInterface;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogCategoryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteRestaurantDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;
use App\Modules\SchedulePhoto\Domain\Record\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\Interface\Http\Mappers\SchedulePhotoRunAdminMapper;
use App\Modules\SchedulePhoto\Interface\Http\Requests\ApplySchedulePhotoRunRequest;
use App\Modules\SchedulePhoto\Interface\Http\Requests\ListSchedulePhotoCatalogRequest;
use App\Modules\SchedulePhoto\Interface\Http\Requests\ListSchedulePhotoRestaurantsRequest;
use App\Modules\SchedulePhoto\Interface\Http\Requests\ResolveSchedulePhotoDiscardedRequest;
use App\Modules\SchedulePhoto\Interface\Http\Requests\SchedulePhotoReadinessRequest;
use App\Modules\SchedulePhoto\Interface\Http\Requests\StartSchedulePhotoRunRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin API «фото графика» для menu_manager.
 *
 * Admin-действия над прогоном только у создателя.
 */
class AdminSchedulePhotoController extends Controller
{
    public function __construct(
        private readonly SchedulePhotoReadinessCheckerInterface $readiness,
        private readonly SchedulePhotoAdminRunInterface $runs,
        private readonly SchedulePhotoRunApplyInterface $applyRuns,
        private readonly SchedulePhotoDiscardedResolverInterface $discarded,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
        private readonly SchedulePhotoAdminViewBuilderInterface $adminView,
        private readonly SchedulePhotoUiOriginConfigBuilderInterface $uiOrigins,
        private readonly SchedulePhotoRunAdminMapper $runAdmin,
    ) {}

    /**
     * GET /api/food/admin/schedule-photo/config — allow-list origin’ов для UI.
     *
     * В селекте: локальный Docker + remote VPS (origin как briskly_sync.source_base_url).
     * localhost остаётся в server allow-list для тестов, в UI не показывается.
     */
    public function config(): JsonResponse
    {
        return response()->json($this->uiOrigins->build()->toArray());
    }

    /**
     * GET /api/food/admin/schedule-photo/restaurants?base_url=
     *
     * Активные рестораны выбранного origin. Id не сверяются с локальными таблицами.
     */
    public function restaurants(ListSchedulePhotoRestaurantsRequest $request): JsonResponse
    {
        $restaurants = $this->photoText->listRestaurants($request->baseUrl());

        return response()->json([
            'restaurants' => array_map(
                static fn (SchedulePhotoRemoteRestaurantDto $restaurant): array => [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                ],
                $restaurants,
            ),
        ]);
    }

    /**
     * GET /api/food/admin/schedule-photo/catalog?base_url=&restaurant_id=
     *
     * Категории каталога выбранного origin для формы. Id ресторана принадлежит этому серверу.
     */
    public function catalog(ListSchedulePhotoCatalogRequest $request): JsonResponse
    {
        $catalog = $this->photoText->getCatalog($request->baseUrl(), $request->restaurantId());

        return response()->json([
            'restaurant_id' => $catalog->restaurantId,
            'restaurant_name' => $catalog->restaurantName,
            'categories' => array_map(
                static fn (SchedulePhotoRemoteCatalogCategoryDto $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                ],
                $catalog->categories,
            ),
        ]);
    }

    /**
     * POST /api/food/admin/schedule-photo/readiness.
     */
    public function readiness(SchedulePhotoReadinessRequest $request): JsonResponse
    {
        $result = $this->readiness->check($request->baseUrl());

        return response()->json($result->toArray(), $result->ok
            ? Response::HTTP_OK
            : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * POST /api/food/admin/schedule-photo/runs.
     */
    public function store(StartSchedulePhotoRunRequest $request): JsonResponse
    {
        $maxUserId = $this->actorMaxUserId($request);
        $photo = $request->photo();
        $storedPath = $photo->getRealPath() ?: $photo->getPathname();

        $run = $this->runs->start(new SchedulePhotoStartRunDto(
            baseUrl: $request->baseUrl(),
            dateFrom: $request->dateFrom(),
            restaurantId: $request->restaurantId(),
            categoryIds: $request->categoryIds(),
            photoAbsolutePath: $storedPath,
            createdByMaxUserId: $maxUserId,
        ));

        return response()->json($this->adminJson($run), Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/food/admin/schedule-photo/runs/{runId}.
     */
    public function show(Request $request, string $runId): JsonResponse
    {
        $run = $this->runs->getForAdmin($runId, $this->actorMaxUserId($request));

        return response()->json($this->adminJson($run));
    }

    /**
     * POST /api/food/admin/schedule-photo/runs/{runId}/apply.
     */
    public function apply(ApplySchedulePhotoRunRequest $request, string $runId): JsonResponse
    {
        $run = $this->applyRuns->apply($runId, $this->actorMaxUserId($request));

        return response()->json($this->adminJson($run));
    }

    /**
     * POST /api/food/admin/schedule-photo/runs/{runId}/resolve-discarded.
     */
    public function resolveDiscarded(ResolveSchedulePhotoDiscardedRequest $request, string $runId): JsonResponse
    {
        $result = $this->discarded->resolveDiscarded($request->toDto($runId), $this->actorMaxUserId($request));
        $payload = $this->adminJson($result->run);
        if ($result->aliasPersistError !== null && $result->aliasPersistError !== '') {
            $payload['alias_persist_error'] = $result->aliasPersistError;
        }

        return response()->json($payload);
    }

    /**
     * max_user_id вызывающего. Без валидного id не создаём и не трогаем прогоны (created_by=0).
     *
     * @throws SchedulePhotoDomainException
     */
    private function actorMaxUserId(Request $request): int
    {
        $maxUserId = (int) ($request->user()?->max_user_id ?? 0);
        if ($maxUserId <= 0) {
            throw new SchedulePhotoDomainException('Нет идентификатора пользователя.', 401);
        }

        return $maxUserId;
    }

    /**
     * @return array<string, mixed>
     */
    private function adminJson(SchedulePhotoRunRecord $run): array
    {
        return $this->runAdmin->toAdminArray($this->adminView->build($run));
    }
}
