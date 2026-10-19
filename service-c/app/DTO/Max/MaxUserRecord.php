<?php

declare(strict_types=1);

namespace App\DTO\Max;

use App\Enums\MessengerChannel;

/**
 * Доменная проекция пользователя MAX без Eloquent.
 */
readonly class MaxUserRecord
{
    public function __construct(
        public int $maxUserId,
        public MessengerChannel $messenger,
        public int $externalUserId,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $languageCode,
        public ?string $photoUrl,
        public ?string $aiAccessUntil,
        public ?int $customerCategoryId,
        public ?string $deliveryAddress,
    ) {}
}
