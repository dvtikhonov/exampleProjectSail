<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Food\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\FoodOrderAdminRepositoryInterface;
use App\Enums\Food\FoodOrderAdminRole;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\Models\MaxUser;
use Illuminate\Contracts\Config\Repository;

/**
 * Аутентификация пользователя MAX mini-app и выдача Sanctum-токена.
 */
class MaxMiniAppAuthService
{
    private const TOKEN_NAME = 'max-miniapp';

    private const TOKEN_ABILITY = 'max-miniapp';

    public function __construct(
        private readonly Repository $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
    ) {}

    /**
     * Создаёт или обновляет пользователя MAX и выдаёт access token.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    public function issueToken(MaxWebAppInitDataDto $initData): array
    {
        $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $initData->maxUserId]);
        $isNewUser = ! $maxUser->exists;

        $maxUser->fill([
            'first_name' => $initData->firstName,
            'last_name' => $initData->lastName,
            'username' => $initData->username,
            'language_code' => $initData->languageCode,
            'photo_url' => $initData->photoUrl,
        ]);

        if ($isNewUser) {
            $maxUser->customer_category_id = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
        }

        $maxUser->save();

        $maxUser->tokens()->where('name', self::TOKEN_NAME)->delete();

        $expiresInSeconds = (int) $this->config->get('max.miniapp.token_ttl_seconds', 86_400);
        $accessToken = $maxUser->createToken(
            self::TOKEN_NAME,
            [self::TOKEN_ABILITY],
            now()->addSeconds($expiresInSeconds),
        );

        $adminRoles = array_map(
            static fn (FoodOrderAdminRole $role): string => $role->value,
            $this->foodOrderAdminRepository->getActiveRoles($maxUser->max_user_id),
        );

        return [
            'token' => $accessToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresInSeconds,
            'user' => [
                'max_user_id' => $maxUser->max_user_id,
                'first_name' => $maxUser->first_name,
                'last_name' => $maxUser->last_name,
                'username' => $maxUser->username,
                'language_code' => $maxUser->language_code,
                'photo_url' => $maxUser->photo_url,
                'admin_roles' => $adminRoles,
            ],
        ];
    }
}
