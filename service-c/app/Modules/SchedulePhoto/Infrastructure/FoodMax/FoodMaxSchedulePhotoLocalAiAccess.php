<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Infrastructure\FoodMax;

use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Shared\ClockInterface;
use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Modules\SchedulePhoto\Application\Port\SchedulePhotoLocalAiAccessInterface;

/**
 * Адаптер Food/Max: статус AI + роль max_manager в локальной базе.
 */
final class FoodMaxSchedulePhotoLocalAiAccess implements SchedulePhotoLocalAiAccessInterface
{
    public function __construct(
        private readonly MaxAiAccessServiceInterface $maxAiAccess,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdmins,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function isGranted(): bool
    {
        $status = $this->maxAiAccess->getStatus($this->clock->now());

        if (! $status->enabled || $status->activeMaxUserId === null) {
            return false;
        }

        return $this->foodOrderAdmins->hasActiveRole(
            $status->activeMaxUserId,
            FoodOrderAdminRole::MaxManager,
        );
    }
}
