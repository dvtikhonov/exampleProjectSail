<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Application\Port;

/**
 * Локальный флаг AI: активный доступ у пользователя с ролью max_manager.
 */
interface SchedulePhotoLocalAiAccessInterface
{
    /**
     * True, если AI-доступ включён и активный пользователь — max_manager.
     */
    public function isGranted(): bool;
}
