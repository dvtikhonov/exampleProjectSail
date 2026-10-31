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

        if (! $maxUser->exists) {
            $conflicting = MaxUser::query()->find($internalMaxUserId);
            // #region agent log
            $this->agentDebugLog('A', 'EloquentMaxUserIdentityRepository.php:upsertMessengerUser', 'firstOrNew miss before insert', [
                'hypothesisId' => 'A',
                'internalMaxUserId' => $internalMaxUserId,
                'messenger' => $messenger->value,
                'externalUserId' => $externalUserId,
                'conflictingFound' => $conflicting !== null,
                'conflictingMessenger' => $conflicting === null
                    ? null
                    : ($conflicting->messenger instanceof MessengerChannel
                        ? $conflicting->messenger->value
                        : (string) $conflicting->messenger),
                'conflictingExternalUserId' => $conflicting?->external_user_id,
            ]);
            // #endregion
            if ($conflicting !== null && $conflicting->messenger !== $messenger) {
                throw new RuntimeException(
                    "Anomaly: max_user_id={$internalMaxUserId} already occupied by messenger={$conflicting->messenger->value}.",
                );
            }

            $maxUser->max_user_id = $internalMaxUserId;
            $maxUser->messenger = $messenger;
            $maxUser->external_user_id = $externalUserId;
        } else {
            // #region agent log
            $this->agentDebugLog('B', 'EloquentMaxUserIdentityRepository.php:upsertMessengerUser', 'firstOrNew hit existing row', [
                'hypothesisId' => 'B',
                'internalMaxUserId' => $internalMaxUserId,
                'existingPk' => (int) $maxUser->max_user_id,
            ]);
            // #endregion
        }

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

        try {
            $maxUser->save();
        } catch (UniqueConstraintViolationException $exception) {
            // #region agent log
            $this->agentDebugLog('A', 'EloquentMaxUserIdentityRepository.php:upsertMessengerUser', 'duplicate primary key on save', [
                'hypothesisId' => 'A',
                'internalMaxUserId' => $internalMaxUserId,
                'modelExistsFlag' => $maxUser->exists,
            ]);
            // #endregion
            throw $exception;
        }

        return $this->mapper->toRecord($maxUser);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function agentDebugLog(string $hypothesisId, string $location, string $message, array $data): void
    {
        // #region agent log
        $payload = json_encode([
            'sessionId' => 'ff9c5e',
            'runId' => 'pre-fix',
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            return;
        }
        $line = $payload."\n";
        foreach ([
            '/home/ddd/exampleProjectSail/.cursor/debug-ff9c5e.log',
            '/var/www/html/storage/logs/debug-ff9c5e.log',
            '/home/ddd/exampleProjectSail/service-c/storage/logs/debug-ff9c5e.log',
        ] as $path) {
            $fh = @fopen($path, 'ab');
            if ($fh === false) {
                continue;
            }
            fwrite($fh, $line);
            fclose($fh);
        }
        // #endregion
    }
}
