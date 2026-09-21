<?php

declare(strict_types=1);

namespace App\Modules\FoodReport\Services;

use App\Modules\FoodReport\Contracts\FoodReportMaxDeliveryInterface;
use Shared\MaxMessenger\Exceptions\MaxMessengerRequestException;

/**
 * Null-адаптер доставки отчёта: messenger driver = null (без HTTP к Bot API).
 */
final class NullFoodReportMaxDelivery implements FoodReportMaxDeliveryInterface
{
    /**
     * {@inheritDoc}
     *
     * @throws MaxMessengerRequestException всегда
     */
    public function deliver(int $maxUserId, string $binary, string $fileName, string $text): void
    {
        throw new MaxMessengerRequestException(
            'Доставка в MAX недоступна (messenger driver = null)',
        );
    }
}
