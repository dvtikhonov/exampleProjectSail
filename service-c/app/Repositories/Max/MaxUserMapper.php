<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\DTO\Max\MaxUserRecord;
use App\Enums\MessengerChannel;
use App\Models\Max\MaxUser;
use DateTimeInterface;

/**
 * Преобразование Eloquent MaxUser в доменную проекцию.
 */
class MaxUserMapper
{
    /**
     * Преобразует модель пользователя MAX в {@see MaxUserRecord}.
     */
    public function toRecord(MaxUser $model): MaxUserRecord
    {
        $messenger = $model->messenger instanceof MessengerChannel
            ? $model->messenger
            : MessengerChannel::from((string) ($model->messenger ?? MessengerChannel::Max->value));

        $externalUserId = $model->external_user_id !== null
            ? (int) $model->external_user_id
            : $messenger->externalUserIdFromInternal((int) $model->max_user_id);

        return new MaxUserRecord(
            maxUserId: (int) $model->max_user_id,
            messenger: $messenger,
            externalUserId: $externalUserId,
            firstName: $model->first_name,
            lastName: $model->last_name,
            username: $model->username,
            languageCode: $model->language_code,
            photoUrl: $model->photo_url,
            aiAccessUntil: $this->formatDateTime($model->ai_access_until),
            customerCategoryId: $model->customer_category_id !== null
                ? (int) $model->customer_category_id
                : null,
            deliveryAddress: $model->delivery_address,
        );
    }

    /**
     * Форматирует дату/время в ISO-8601 или null.
     */
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
