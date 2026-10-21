<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\DTO\Max\MessengerUserIdentity;
use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Enums\MessengerChannel;

/**
 * Идентичность пользователей mini-app (поиск и upsert из initData).
 */
interface MaxUserIdentityRepositoryInterface
{
    /**
     * Находит пользователя по внутреннему max_user_id.
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord;

    /**
     * Находит пользователя по каналу мессенджера и внешнему id.
     */
    public function findByMessengerAndExternalUserId(
        MessengerChannel $messenger,
        int $externalUserId,
    ): ?MaxUserRecord;

    /**
     * Канал и внешний id для notify по внутреннему max_user_id.
     */
    public function resolveMessengerIdentity(int $maxUserId): ?MessengerUserIdentity;

    /**
     * Создаёт или обновляет профиль пользователя из initData MAX WebApp.
     *
     * @param  int|null  $defaultCustomerCategoryId  категория, если у пользователя ещё нет
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;

    /**
     * Создаёт или обновляет профиль пользователя из initData Telegram WebApp.
     *
     * Внутренний max_user_id = OFFSET + telegram_user_id.
     *
     * @param  int|null  $defaultCustomerCategoryId  категория, если у пользователя ещё нет
     */
    public function upsertFromTelegramInitData(
        TelegramWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;

    /**
     * Создаёт или обновляет профиль пользователя из данных Telegram Bot API (message.from).
     *
     * Внутренний max_user_id = OFFSET + telegram_user_id.
     *
     * @param  int|null  $defaultCustomerCategoryId  категория, если у пользователя ещё нет
     */
    public function upsertFromTelegramBotProfile(
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord;
}
