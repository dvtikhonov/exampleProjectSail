<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\MaxUser;

trait AuthenticatesMaxMiniAppUser
{
    protected function authenticateMaxUser(?MaxUser $maxUser = null): array
    {
        $maxUser ??= MaxUser::query()->create([
            'max_user_id' => 99_001,
            'first_name' => 'FoodTester',
        ]);

        $token = $maxUser->createToken('max-miniapp', ['max-miniapp'], now()->addHour())->plainTextToken;

        return [
            'user' => $maxUser,
            'token' => $token,
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ];
    }
}
