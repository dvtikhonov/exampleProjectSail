<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\Food\Review\FoodOrderAdminRole;
use App\Models\Max\MaxUser;
use Illuminate\Support\Carbon;

/**
 * Общие хелперы PhotoText agent Feature-тестов.
 *
 * Ожидает константу AGENT_TOKEN в использующем классе (для вызовов без явного $token)
 * и trait AuthenticatesMaxMiniAppUser (для phototextManager).
 */
trait ConfiguresPhotoTextAgent
{
    /**
     * @return array<string, string>
     */
    protected function photoTextHeaders(?string $token = null): array
    {
        return [
            'X-PhotoText-Token' => $token ?? static::AGENT_TOKEN,
        ];
    }

    /** Настраивает agent token, manager_max_user_id и активный ai_access_until. */
    protected function configurePhotoTextAgent(int $managerMaxUserId, ?string $token = null): void
    {
        config([
            'phototext.agent_token' => $token ?? static::AGENT_TOKEN,
            'phototext.manager_max_user_id' => $managerMaxUserId,
        ]);

        MaxUser::query()
            ->where('max_user_id', $managerMaxUserId)
            ->update([
                'ai_access_until' => Carbon::now()->addMinutes(30),
            ]);
    }

    /**
     * @return array{user: MaxUser, headers: array<string, string>}
     */
    protected function phototextManager(int $maxUserId, string $firstName): array
    {
        return $this->asFoodOrderAdmin(
            $this->authenticateMaxUser(MaxUser::query()->create([
                'max_user_id' => $maxUserId,
                'first_name' => $firstName,
            ])),
            FoodOrderAdminRole::MaxManager,
        );
    }
}
