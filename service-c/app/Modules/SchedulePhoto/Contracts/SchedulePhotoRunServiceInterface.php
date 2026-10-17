<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Contracts;

use App\Exceptions\Food\FoodDomainException;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoOcrCallbackDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoResolveDiscardedDto;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoRunRecord;
use App\Modules\SchedulePhoto\DTO\SchedulePhotoStartRunDto;

/**
 * Application-сервис прогонов «фото графика»: start → OCR → match → apply.
 */
interface SchedulePhotoRunServiceInterface
{
    /**
     * Проверяет readiness, создаёт run и ставит OCR job.
     *
     * @throws FoodDomainException readiness fail / валидация scope
     */
    public function start(SchedulePhotoStartRunDto $dto): SchedulePhotoRunRecord;

    /**
     * Возвращает прогон или кидает 404-доменную ошибку.
     *
     * Внутренний OCR handshake. Проверки владельца нет.
     *
     * @throws FoodDomainException
     */
    public function get(string $runId): SchedulePhotoRunRecord;

    /**
     * Прогон для admin-чтения: только создатель.
     * Чужой прогон неотличим от отсутствующего (одна и та же 404).
     *
     * @throws FoodDomainException
     */
    public function getForAdmin(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord;

    /**
     * Handshake OCR в queue job (sidecar start).
     *
     * @throws FoodDomainException
     */
    public function performQueuedOcr(string $runId): void;

    /**
     * Колбэк sidecar: entries → schedule/match на base_url прогона → matched/issues.
     *
     * @throws FoodDomainException
     */
    public function completeOcr(SchedulePhotoOcrCallbackDto $dto): SchedulePhotoRunRecord;

    /**
     * Apply только matched после human-confirm (matched_count > 0).
     * Только создатель; чужой прогон неотличим от отсутствующего.
     *
     * @throws FoodDomainException
     */
    public function apply(string $runId, int $actorMaxUserId): SchedulePhotoRunRecord;

    /**
     * Human-resolve discarded: канон (алиас или dish_name) + даты → rematch, строка уходит из discarded.
     * Только создатель; чужой прогон неотличим от отсутствующего.
     * Актёр передаётся отдельно от тела запроса.
     *
     * @throws FoodDomainException
     */
    public function resolveDiscarded(SchedulePhotoResolveDiscardedDto $dto, int $actorMaxUserId): SchedulePhotoRunRecord;

    /**
     * Admin JSON: run + enriched discarded (suggested_canon) + catalog_scope для UI.
     *
     * @return array<string, mixed>
     *
     * @throws FoodDomainException
     */
    public function toAdminArray(SchedulePhotoRunRecord $run): array;

    /**
     * Помечает прогон failed (timeout / падение job).
     *
     * @param  string|null  $reason  текст ошибки handshake; пусто — общая формулировка timeout
     */
    public function failQueuedOcr(string $runId, ?string $reason = null): void;
}
