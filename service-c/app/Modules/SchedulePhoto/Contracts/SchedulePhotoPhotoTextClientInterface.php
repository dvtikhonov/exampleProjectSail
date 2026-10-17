<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\DTO\Food\PhotoText\PhotoTextScheduleEntryDto;
use App\DTO\Food\PhotoText\PhotoTextScheduleResultDto;
use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteCatalogDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRemoteRestaurantDto;

/**
 * HTTP-клиент PhotoText на origin из allow-list BASE_URL.
 *
 * Токены остаются на сервере: agent — на чтение и match, write — только на apply.
 */
interface SchedulePhotoPhotoTextClientInterface
{
    /**
     * Активные рестораны origin: GET /api/food/phototext/restaurants.
     *
     * @return list<SchedulePhotoRemoteRestaurantDto>
     *
     * @throws FoodDomainException пустой или запрещённый origin, 401/422 и прочие ошибки PhotoText
     */
    public function listRestaurants(string $baseUrl): array;

    /**
     * Каталог ресторана: GET /api/food/phototext/catalog?restaurant_id=.
     *
     * @throws FoodDomainException пустой или запрещённый origin, restaurant_id < 1, ошибки PhotoText
     */
    public function getCatalog(string $baseUrl, int $restaurantId): SchedulePhotoRemoteCatalogDto;

    /**
     * Exact match графика без записи: POST /api/food/phototext/schedule/match (только agent token).
     *
     * @param  list<int>|null  $categoryIds  null или пустой список — ключ category_ids не отправляется
     * @param  list<PhotoTextScheduleEntryDto>  $entries
     *
     * @throws FoodDomainException пустой или запрещённый origin, 401/422 и прочие ошибки PhotoText
     */
    public function match(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto;

    /**
     * Запись графика: POST /api/food/phototext/schedule/apply (agent token и write token).
     *
     * @param  list<int>|null  $categoryIds  null или пустой список — ключ category_ids не отправляется
     * @param  list<PhotoTextScheduleEntryDto>  $entries
     *
     * @throws FoodDomainException пустой или запрещённый origin, пустой write token, 401/422 и прочие ошибки PhotoText
     */
    public function apply(
        string $baseUrl,
        int $restaurantId,
        ?array $categoryIds,
        string $dateFrom,
        string $dateTo,
        array $entries,
    ): PhotoTextScheduleResultDto;
}
