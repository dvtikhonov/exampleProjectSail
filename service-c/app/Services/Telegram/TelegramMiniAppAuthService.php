<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use App\Contracts\Food\Delivery\CustomerCategoryRepositoryInterface;
use App\Contracts\Food\Order\FoodOrderAdminRepositoryInterface;
use App\Contracts\Max\MaxMiniAppTokenIssuerInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Shared\ApplicationConfigInterface;
use App\Contracts\Shared\ClockInterface;
use App\Contracts\Telegram\TelegramMiniAppAuthServiceInterface;
use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Enums\Food\Review\FoodOrderAdminRole;
use DateInterval;

/**
 * Аутентификация пользователя Telegram Mini App и выдача Sanctum-токена.
 */
class TelegramMiniAppAuthService implements TelegramMiniAppAuthServiceInterface
{
    private const TOKEN_NAME = 'telegram-miniapp';

    private const TOKEN_ABILITY = 'telegram-miniapp';

    public function __construct(
        private readonly ApplicationConfigInterface $config,
        private readonly CustomerCategoryRepositoryInterface $customerCategoryRepository,
        private readonly FoodOrderAdminRepositoryInterface $foodOrderAdminRepository,
        private readonly MaxUserIdentityRepositoryInterface $maxUserRepository,
        private readonly MaxMiniAppTokenIssuerInterface $tokenIssuer,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function issueToken(TelegramWebAppInitDataDto $initData): array
    {
        $defaultCategoryId = $this->customerCategoryRepository->findOrCreateDefaultCategoryId();
        $maxUser = $this->maxUserRepository->upsertFromTelegramInitData($initData, $defaultCategoryId);

        $this->tokenIssuer->revokeNamedTokens($maxUser->maxUserId, self::TOKEN_NAME);

        $expiresInSeconds = (int) $this->config->get('telegram.miniapp.token_ttl_seconds', 3_600);
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
