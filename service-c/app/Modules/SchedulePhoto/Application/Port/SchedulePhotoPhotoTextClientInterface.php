<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteCatalogDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoRemoteRestaurantDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleEntryDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoScheduleResultDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * HTTP-клиент PhotoText на origin из allow-list BASE_URL.
 *
 * Токены остаются на сервере: agent — на чтение и match, write — только на apply.
 * Редиректы не следуются: 301/302 — ошибка origin, Location не читается.
 */
interface SchedulePhotoPhotoTextClientInterface
{
    /**
     * Активные рестораны origin: GET /api/food/phototext/restaurants.
     *
     * @return list<SchedulePhotoRemoteRestaurantDto>
     *
     * @throws SchedulePhotoDomainException пустой или запрещённый origin, 401/422 и прочие ошибки PhotoText
     */
    public function listRestaurants(string $baseUrl): array;

    /**
     * Каталог ресторана: GET /api/food/phototext/catalog?restaurant_id=.
     *
     * @throws SchedulePhotoDomainException пустой или запрещённый origin, restaurant_id < 1, ошибки PhotoText
     */
    public function getCatalog(string $baseUrl, int $restaurantId): SchedulePhotoRemoteCatalogDto;

    /**
     * Exact match графика без записи: POST /api/food/phototext/schedule/match (только agent token).
     *
     * @param  list<int>|null  $categoryIds  null или пустой список — ключ category_ids не отправляется
     * @param  list<SchedulePhotoScheduleEntryDto>  $entries
     *
     * @throws SchedulePhotoDomainException пустой или запрещённый origin, 401/422 и прочие ошибки PhotoText
     */
    public function match(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): SchedulePhotoScheduleResultDto;

    /**
     * Запись графика: POST /api/food/phototext/schedule/apply (agent token и write token).
     *
     * @param  list<int>|null  $categoryIds  null или пустой список — ключ category_ids не отправляется
     * @param  list<SchedulePhotoScheduleEntryDto>  $entries
     *
     * @throws SchedulePhotoDomainException пустой или запрещённый origin, пустой write token, 401/422 и прочие ошибки PhotoText
     */
    public function apply(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): SchedulePhotoScheduleResultDto;
}
