<?php

declare(strict_types=1);

namespace App\DTO\Food;

/**
 * Краткие данные пользователя MAX для выбора потребителя при ручном заказе.
 */
readonly class ManualOrderUserDto
{
    public function __construct(
        public int $maxUserId,
        public ?string $firstName,
        public ?string $lastName,
        public ?string $username,
        public ?string $deliveryAddress,
    ) {}

    /**
     * Преобразует пользователя в массив для JSON-ответа API.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(): array
    {
        return [
            'max_user_id' => $this->maxUserId,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'username' => $this->username,
            'delivery_address' => $this->deliveryAddress,
        ];
    }
}
