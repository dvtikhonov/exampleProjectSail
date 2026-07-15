<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Food\FoodOrderAdminRole;
use App\Models\FoodOrderAdmin;
use App\Models\MaxUser;

trait AuthenticatesMaxMiniAppUser
{
    /** Аутентифицирует пользователя MAX мини-приложения. */
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

    /**
     * @param  array{user: MaxUser, headers: array<string, string>}  $auth
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    protected function asFoodOrderAdmin(array $auth, FoodOrderAdminRole $role): array
    {
        FoodOrderAdmin::query()->updateOrCreate(
            [
                'max_user_id' => $auth['user']->max_user_id,
                'role' => $role,
            ],
            [
                'is_active' => true,
            ],
        );

        return $auth;
    }
}
