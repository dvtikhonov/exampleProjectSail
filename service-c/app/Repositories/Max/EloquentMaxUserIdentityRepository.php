<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\DTO\Max\MessengerUserIdentity;
use App\DTO\Telegram\TelegramWebAppInitDataDto;
use App\Enums\MessengerChannel;
use App\Models\Max\MaxUser;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Eloquent-реализация identity-порта пользователей MAX / Telegram.
 */
class EloquentMaxUserIdentityRepository implements MaxUserIdentityRepositoryInterface
{
    public function __construct(
        private readonly MaxUserMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord
    {
        $model = MaxUser::query()->find($maxUserId);

        return $model !== null ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function findByMessengerAndExternalUserId(
        MessengerChannel $messenger,
        int $externalUserId,
    ): ?MaxUserRecord {
        $model = MaxUser::query()
            ->where('messenger', $messenger->value)
            ->where('external_user_id', $externalUserId)
            ->first();

        return $model !== null ? $this->mapper->toRecord($model) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function resolveMessengerIdentity(int $maxUserId): ?MessengerUserIdentity
    {
        $model = MaxUser::query()->find($maxUserId);
        if ($model === null) {
            return null;
        }

        $messenger = $model->messenger instanceof MessengerChannel
            ? $model->messenger
            : MessengerChannel::from((string) ($model->messenger ?? MessengerChannel::Max->value));

        $externalUserId = $model->external_user_id !== null
            ? (int) $model->external_user_id
            : $messenger->externalUserIdFromInternal((int) $model->max_user_id);

        return new MessengerUserIdentity(
            maxUserId: (int) $model->max_user_id,
            messenger: $messenger,
            externalUserId: $externalUserId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->upsertMessengerUser(
            messenger: MessengerChannel::Max,
            externalUserId: $initData->maxUserId,
            firstName: $initData->firstName,
            lastName: $initData->lastName,
            username: $initData->username,
            languageCode: $initData->languageCode,
            photoUrl: $initData->photoUrl,
            defaultCustomerCategoryId: $defaultCustomerCategoryId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromTelegramInitData(
        TelegramWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->upsertMessengerUser(
            messenger: MessengerChannel::Telegram,
            externalUserId: $initData->telegramUserId,
            firstName: $initData->firstName,
            lastName: $initData->lastName,
            username: $initData->username,
            languageCode: $initData->languageCode,
            photoUrl: $initData->photoUrl,
            defaultCustomerCategoryId: $defaultCustomerCategoryId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromTelegramBotProfile(
        int $telegramUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->upsertMessengerUser(
            messenger: MessengerChannel::Telegram,
            externalUserId: $telegramUserId,
            firstName: $firstName,
            lastName: $lastName,
            username: $username,
            languageCode: $languageCode,
            photoUrl: null,
            defaultCustomerCategoryId: $defaultCustomerCategoryId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromMaxBotProfile(
        int $maxUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->upsertMessengerUser(
            messenger: MessengerChannel::Max,
            externalUserId: $maxUserId,
            firstName: $firstName,
            lastName: $lastName,
            username: $username,
            languageCode: $languageCode,
            photoUrl: null,
            defaultCustomerCategoryId: $defaultCustomerCategoryId,
        );
    }

    /**
     * Общий upsert по каналу мессенджера и внешнему id.
     */
    private function upsertMessengerUser(
        MessengerChannel $messenger,
        int $externalUserId,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
        ?string $photoUrl,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        $internalMaxUserId = $messenger->toInternalMaxUserId($externalUserId);

        $maxUser = MaxUser::query()->firstOrNew([
            'messenger' => $messenger->value,
            'external_user_id' => $externalUserId,
        ]);

        $existedByExternal = $maxUser->exists;
        $lookupBranch = $existedByExternal ? 'found_by_messenger_external' : 'create';

        if (! $maxUser->exists) {
            $conflicting = MaxUser::query()->find($internalMaxUserId);
            if ($conflicting !== null && $conflicting->messenger !== $messenger) {
                throw new RuntimeException(
                    "Anomaly: max_user_id={$internalMaxUserId} already occupied by messenger={$conflicting->messenger->value}.",
                );
            }

            if ($conflicting !== null) {
                $maxUser = $conflicting;
                $lookupBranch = 'found_by_internal_pk';
            } else {
                $maxUser->max_user_id = $internalMaxUserId;
                $maxUser->messenger = $messenger;
                $maxUser->external_user_id = $externalUserId;
                $lookupBranch = 'create';
            }
        }

        // #region agent log
        if ($messenger === MessengerChannel::Telegram) {
            $rowMessenger = $maxUser->messenger instanceof MessengerChannel
                ? $maxUser->messenger->value
                : (string) $maxUser->messenger;
            $this->agentDebugLog('H1', 'EloquentMaxUserIdentityRepository.php', 'telegram upsert lookup', [
                'existed_by_messenger_external' => $existedByExternal,
                'lookup_branch' => $lookupBranch,
                'external_user_id' => $externalUserId,
                'internal_max_user_id' => $internalMaxUserId,
                'row_max_user_id' => $maxUser->max_user_id !== null ? (int) $maxUser->max_user_id : null,
                'row_messenger' => $rowMessenger,
                'row_external_user_id' => $maxUser->external_user_id !== null ? (int) $maxUser->external_user_id : null,
                'offset' => MessengerChannel::TELEGRAM_MAX_USER_ID_OFFSET,
                'offset_matches_row' => (int) $maxUser->max_user_id === $internalMaxUserId,
            ]);
        }
        // #endregion

        $this->fillMessengerProfile(
            $maxUser,
            $firstName,
            $lastName,
            $username,
            $languageCode,
            $photoUrl,
            $defaultCustomerCategoryId,
        );

        try {
            $maxUser->save();
        } catch (UniqueConstraintViolationException $exception) {
            $reloaded = $this->findExistingMessengerUser($messenger, $externalUserId, $internalMaxUserId);
            if ($reloaded === null) {
                throw $exception;
            }

            if ($reloaded->messenger !== $messenger) {
                throw new RuntimeException(
                    "Anomaly: max_user_id={$internalMaxUserId} already occupied by messenger={$reloaded->messenger->value}.",
                );
            }

            $maxUser = $reloaded;
            $this->fillMessengerProfile(
                $maxUser,
                $firstName,
                $lastName,
                $username,
                $languageCode,
                $photoUrl,
                $defaultCustomerCategoryId,
            );
            $maxUser->save();
        }

        return $this->mapper->toRecord($maxUser);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        // #region agent log
        try {
            $payload = [
                'sessionId' => '87a53d',
                'runId' => 'post-fix',
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ];
            $line = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if (is_string($line)) {
                @file_put_contents('/home/ddd/exampleProjectSail/.cursor/debug-87a53d.log', $line."\n", FILE_APPEND);
            }
            Log::info('telegram_connect_probe', $payload);
        } catch (\Throwable) {
        }
        // #endregion
    }

    /**
     * Заполняет профиль и категорию по умолчанию, не затирая уже выбранную категорию.
     */
    private function fillMessengerProfile(
        MaxUser $maxUser,
        string $firstName,
        ?string $lastName,
        ?string $username,
        ?string $languageCode,
        ?string $photoUrl,
        ?int $defaultCustomerCategoryId,
    ): void {
        $maxUser->fill([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'language_code' => $languageCode,
            'photo_url' => $photoUrl,
        ]);

        if ($maxUser->customer_category_id === null && $defaultCustomerCategoryId !== null) {
            $maxUser->customer_category_id = $defaultCustomerCategoryId;
        }
    }

    /**
     * Ищет строку, которую параллельный bot_started уже успел вставить.
     */
    private function findExistingMessengerUser(
        MessengerChannel $messenger,
        int $externalUserId,
        int $internalMaxUserId,
    ): ?MaxUser {
        $byExternalId = MaxUser::query()
            ->where('messenger', $messenger->value)
            ->where('external_user_id', $externalUserId)
            ->first();

        if ($byExternalId !== null) {
            return $byExternalId;
        }

        return MaxUser::query()->find($internalMaxUserId);
    }
}
