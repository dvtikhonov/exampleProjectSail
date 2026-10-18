<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Contract;

use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoResolveDiscardedDto;
use App\Modules\SchedulePhoto\Application\Dto\SchedulePhotoResolveResultDto;
use App\Modules\SchedulePhoto\Domain\Exception\SchedulePhotoDomainException;

/**
 * Human-resolve отброшенной OCR-строки: канон и даты → повторный match.
 */
interface SchedulePhotoDiscardedResolverInterface
{
    /**
     * Канон (алиас или dish_name) и даты → rematch, строка уходит из discarded.
     * Только создатель; чужой прогон неотличим от отсутствующего.
     * Актёр передаётся отдельно от тела запроса.
     * Пара в словарь пишется только после успешного update и только при ручном dish_name.
     * Сбой записи словаря — soft-fail: прогон уже обновлён, aliasPersistError в результате.
     *
     * @throws SchedulePhotoDomainException
     */
    public function resolveDiscarded(SchedulePhotoResolveDiscardedDto $dto, int $actorMaxUserId): SchedulePhotoResolveResultDto;
}
