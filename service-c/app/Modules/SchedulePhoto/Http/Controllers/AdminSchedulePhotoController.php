<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoPhotoTextClientInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoReadinessCheckerInterface;
use App\Modules\SchedulePhoto\Contracts\SchedulePhotoRunServiceInterface;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteCatalogCategoryDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteRestaurantDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoStartRunDto;
use App\Modules\SchedulePhoto\Http\Requests\ApplySchedulePhotoRunRequest;
use App\Modules\SchedulePhoto\Http\Requests\ListSchedulePhotoCatalogRequest;
use App\Modules\SchedulePhoto\Http\Requests\ListSchedulePhotoRestaurantsRequest;
use App\Modules\SchedulePhoto\Http\Requests\ResolveSchedulePhotoDiscardedRequest;
use App\Modules\SchedulePhoto\Http\Requests\SchedulePhotoReadinessRequest;
use App\Modules\SchedulePhoto\Http\Requests\StartSchedulePhotoRunRequest;
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
        private readonly SchedulePhotoRunServiceInterface $runs,
        private readonly SchedulePhotoPhotoTextClientInterface $photoText,
    ) {}

    /**
     * GET /api/food/admin/schedule-photo/config — allow-list origin’ов для UI.
     *
     * В селекте: локальный Docker + remote VPS (origin как briskly_sync.source_base_url).
     * localhost остаётся в server allow-list для тестов, в UI не показывается.
     */
    public function config(): JsonResponse
    {
        $local = rtrim((string) config('schedule_photo.local_base_url', ''), '/');
        $default = rtrim((string) config('schedule_photo.default_base_url', ''), '/');
        /** @var list<string>|mixed $allowedRaw */
        $allowedRaw = config('schedule_photo.allowed_base_urls', []);
        $allowed = is_array($allowedRaw) ? $allowedRaw : [];

        /** @var list<array{value: string, label: string}> $uiOptions */
        $uiOptions = [];
        $seen = [];

        foreach ($allowed as $origin) {
            if (! is_string($origin) || trim($origin) === '') {
                continue;
            }

            $value = rtrim(trim($origin), '/');
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $host = strtolower((string) (parse_url($value, PHP_URL_HOST) ?: ''));
            if ($host === 'localhost' || $host === '127.0.0.1') {
                continue;
            }

            $port = parse_url($value, PHP_URL_PORT);
            $hostLabel = $host.($port !== null && $port !== false ? ':'.$port : '');
            $isDockerLocal = $host === 'host.docker.internal';

            $seen[$value] = true;
            $uiOptions[] = [
                'value' => $value,
                'label' => $isDockerLocal
                    ? 'Локальный ('.$hostLabel.')'
                    : 'VPS ('.$hostLabel.')',
            ];
        }

        $defaultForUi = isset($seen[$default])
            ? $default
            : (isset($seen[$local]) ? $local : (string) ($uiOptions[0]['value'] ?? $default));

        return response()->json([
            'default_base_url' => $defaultForUi,
            'allowed_base_urls' => $uiOptions,
        ]);
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

        return response()->json($this->runs->toAdminArray($run), Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/food/admin/schedule-photo/runs/{runId}.
     */
    public function show(Request $request, string $runId): JsonResponse
    {
        $run = $this->runs->getForAdmin($runId, $this->actorMaxUserId($request));

        return response()->json($this->runs->toAdminArray($run));
    }

    /**
     * POST /api/food/admin/schedule-photo/runs/{runId}/apply.
     */
    public function apply(ApplySchedulePhotoRunRequest $request, string $runId): JsonResponse
    {
        $run = $this->runs->apply($runId, $this->actorMaxUserId($request));

        return response()->json($this->runs->toAdminArray($run));
    }

    /**
     * POST /api/food/admin/schedule-photo/runs/{runId}/resolve-discarded.
     */
    public function resolveDiscarded(ResolveSchedulePhotoDiscardedRequest $request, string $runId): JsonResponse
    {
        $run = $this->runs->resolveDiscarded($request->toDto($runId), $this->actorMaxUserId($request));

        return response()->json($this->runs->toAdminArray($run));
    }

    /**
     * max_user_id вызывающего. Без пользователя — 0: чужой и анонимный прогон
     * для admin-действий неотличимы от отсутствующего.
     */
    private function actorMaxUserId(Request $request): int
    {
        return (int) ($request->user()?->max_user_id ?? 0);
    }
}
