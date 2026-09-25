<?php

declare(strict_types=1);

namespace App\Modules\MaxIncomingRelay\DTO;

use DateTimeImmutable;

/**
 * Краткие данные последнего заказа для текста уведомления.
 *
 * Дата в уведомлении форматируется билдером в Europe/Moscow как dd.mm.yyyy;
 * номер — id заказа (как «Заказ №N» в остальных уведомлениях).
 */
readonly class LastOrderSummaryDto
{
    public function __construct(
        public int $id,
        public DateTimeImmutable $createdAt,
    ) {}
}
