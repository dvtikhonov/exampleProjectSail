<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

/**
 * Порт идентификатора прогона SchedulePhoto (UUID v4).
 */
interface SchedulePhotoIdGeneratorInterface
{
    /**
     * Новый UUID v4.
     */
    public function generate(): string;
}
