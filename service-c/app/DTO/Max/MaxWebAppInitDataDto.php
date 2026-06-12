<?php

declare(strict_types=1);

namespace App\DTO\Max;

readonly class MaxWebAppInitDataDto
{
    /**
     * @param  array<string, mixed>|null  $chat
     */
    public function __construct(
        public int $maxUserId,
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $languageCode,
        public ?string $photoUrl,
        public int $authDate,
        public ?string $queryId = null,
        public ?string $ip = null,
        public ?array $chat = null,
    ) {}
}
