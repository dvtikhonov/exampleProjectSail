<?php

declare(strict_types=1);

namespace App\DTO\Max;

/**
 * Пара max_user_id + Sanctum Bearer-токен для одного VU нагрузки.
 */
readonly class LoadTestTokenDto
{
    public function __construct(
        public int $maxUserId,
        public string $token,
    ) {}

    /**
     * @return array{max_user_id: int, token: string}
     */
    public function toArray(): array
    {
        return [
            'max_user_id' => $this->maxUserId,
            'token' => $this->token,
        ];
    }
}
