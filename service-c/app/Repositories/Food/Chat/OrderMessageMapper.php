<?php

declare(strict_types=1);

namespace App\Repositories\Food\Chat;

use App\DTO\Food\Chat\OrderMessageRecord;
use App\Models\Food\FoodOrderMessage;
use DateTimeInterface;

/**
 * Преобразование между Eloquent-сообщением чата и доменным Record.
 */
class OrderMessageMapper
{
    /**
     * Преобразует модель сообщения в доменную проекцию.
     */
    public function toRecord(FoodOrderMessage $model): OrderMessageRecord
    {
        return new OrderMessageRecord(
            id: (int) $model->id,
            foodOrderId: (int) $model->food_order_id,
            senderMaxUserId: (int) $model->sender_max_user_id,
            body: (string) $model->body,
            createdAt: $this->formatDateTime($model->created_at) ?? now()->toIso8601String(),
            senderFirstName: $model->relationLoaded('sender') ? $model->sender?->first_name : null,
            senderLastName: $model->relationLoaded('sender') ? $model->sender?->last_name : null,
            senderUsername: $model->relationLoaded('sender') ? $model->sender?->username : null,
        );
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        return (string) $value;
    }
}
