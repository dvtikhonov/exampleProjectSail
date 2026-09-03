<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxUserDeliveryAddressInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Max\MaxUserRecord;

/**
 * Хранение и чтение адреса доставки пользователя MAX.
 */
class MaxUserDeliveryAddressService implements MaxUserDeliveryAddressInterface
{
    public function __construct(
        private readonly MaxUserRepositoryInterface $maxUserRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function defaultFor(MaxUserRecord $maxUser): ?string
    {
        $address = $maxUser->deliveryAddress;

        if ($address === null) {
            return null;
        }

        $trimmed = trim($address);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * {@inheritDoc}
     */
    public function defaultForMaxUserId(int $maxUserId): ?string
    {
        $maxUser = $this->maxUserRepository->findByMaxUserId($maxUserId);

        if ($maxUser === null) {
            return null;
        }

        return $this->defaultFor($maxUser);
    }

    /**
     * {@inheritDoc}
     */
    public function persist(MaxUserRecord $maxUser, string $deliveryAddress): void
    {
        $trimmed = trim($deliveryAddress);

        if ($trimmed === '') {
            return;
        }

        if ($this->defaultFor($maxUser) === $trimmed) {
            return;
        }

        $this->maxUserRepository->updateDeliveryAddress($maxUser->maxUserId, $trimmed);
    }

    /**
     * {@inheritDoc}
     */
    public function persistForMaxUserId(int $maxUserId, string $deliveryAddress): void
    {
        $maxUser = $this->maxUserRepository->findByMaxUserId($maxUserId);

        if ($maxUser === null) {
            return;
        }

        $this->persist($maxUser, $deliveryAddress);
    }
}
