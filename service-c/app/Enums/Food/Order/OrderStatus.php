<?php

declare(strict_types=1);

namespace App\Enums\Food\Order;

/**
 * Статусы заказа еды.
 */
enum OrderStatus: string
{
    /** @deprecated Используется только для обратной совместимости до полного перехода на pending_review */
    case Submitted = 'submitted';

    /** Черновик заказа после сканирования фото заказа */
    case DraftAfterScanning = 'draft_after_scanning';

    case PendingReview = 'pending_review';
    case AwaitingComposition = 'awaiting_composition';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
