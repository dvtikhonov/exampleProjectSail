<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Models\MaxUser;

class MaxUserDeliveryAddressService
{
    public function defaultFor(MaxUser $maxUser): ?string
    {
        $address = $maxUser->delivery_address;

        if ($address === null) {
            return null;
        }

        $trimmed = trim($address);

        return $trimmed === '' ? null : $trimmed;
    }

    public function persist(MaxUser $maxUser, string $deliveryAddress): void
    {
        $trimmed = trim($deliveryAddress);

        if ($trimmed === '') {
            return;
        }

        if ($this->defaultFor($maxUser) === $trimmed) {
            return;
        }

        $maxUser->update(['delivery_address' => $trimmed]);
    }
}
