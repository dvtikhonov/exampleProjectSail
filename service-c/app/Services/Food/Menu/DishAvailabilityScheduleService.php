<?php

declare(strict_types=1);

namespace App\Services\Food\Menu;

use App\Contracts\Food\Menu\DishAvailabilityGridServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleServiceInterface;
use App\Contracts\Food\Menu\DishAvailabilityScheduleWriterInterface;
use App\DTO\Food\Menu\DishAvailabilityGridDto;
use App\DTO\Food\Menu\DishAvailabilityUpdateDto;

/**
 * Facade: график доступности блюд — чтение сетки и синхронизация дат.
 *
 * Делегирует в {@see DishAvailabilityGridService} и {@see DishAvailabilityScheduleWriter}.
 */
class DishAvailabilityScheduleService implements DishAvailabilityScheduleServiceInterface
{
    public function __construct(
        private readonly DishAvailabilityGridServiceInterface $gridService,
        private readonly DishAvailabilityScheduleWriterInterface $scheduleWriter,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getGrid(
        int $restaurantId,
        int $categoryId,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): DishAvailabilityGridDto {
        return $this->gridService->getGrid($restaurantId, $categoryId, $dateFrom, $dateTo);
    }

    /**
     * {@inheritDoc}
     */
    public function syncSchedule(DishAvailabilityUpdateDto $dto): void
    {
        $this->scheduleWriter->syncSchedule($dto);
    }
}
