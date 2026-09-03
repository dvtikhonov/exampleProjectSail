<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxMiniAppAuthServiceInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ClockInterface;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use DateInterval;

/**
 * Аутентификация пользователя MAX mini-app и выдача Sanctum-токена.
 */
class MaxMiniAppAuthService implements MaxMiniAppAuthServiceInterface
{
    private const TOKEN_NAME = 'max-miniapp';

    private const TOKEN_ABILITY = 'max-miniapp';

    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly MaxUserRepositoryInterface $maxUserRepository,
        private readonly MaxMiniAppTokenIssuerInterface $tokenIssuer,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * Создаёт или обновляет пользователя MAX и выдаёт access token.
     *
     * @return array{token: string, token_type: string, expires_in: int, user: array<string, mixed>}
     */
    public function issueToken(MaxWebAppInitDataDto $initData): array
    {
        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
        $maxUser = $this->maxUserRepository->upsertFromInitData($initData, $defaultCategoryId);

        $this->tokenIssuer->revokeNamedTokens($maxUser->maxUserId, self::TOKEN_NAME);

        $expiresInSeconds = (int) $this->config->get('max.miniapp.token_ttl_seconds', 86_400);
        $expiresAt = $this->clock->now()->add(new DateInterval('PT'.$expiresInSeconds.'S'));

        $plainTextToken = $this->tokenIssuer->createToken(
            $maxUser->maxUserId,
            self::TOKEN_NAME,
            [self::TOKEN_ABILITY],
            $expiresAt,
        );

        $adminRoles = array_map(
            static fn (FoodOrderAdminRole $role): string => $role->value,
            $this->foodOrderAdminRepository->getActiveRoles($maxUser->maxUserId),
        );

        return [
            'token' => $plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresInSeconds,
            'user' => [
                'max_user_id' => $maxUser->maxUserId,
                'first_name' => $maxUser->firstName,
                'last_name' => $maxUser->lastName,
                'username' => $maxUser->username,
                'language_code' => $maxUser->languageCode,
                'photo_url' => $maxUser->photoUrl,
                'admin_roles' => $adminRoles,
            ],
        ];
    }
}
