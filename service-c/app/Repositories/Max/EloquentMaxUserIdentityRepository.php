<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\Models\Max\MaxUser;

/**
 * Eloquent-реализация identity-порта пользователей MAX.
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
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $initData->maxUserId]);

        $maxUser->fill([
            'first_name' => $initData->firstName,
            'last_name' => $initData->lastName,
            'username' => $initData->username,
            'language_code' => $initData->languageCode,
            'photo_url' => $initData->photoUrl,
        ]);

        if ($maxUser->customer_category_id === null && $defaultCustomerCategoryId !== null) {
            $maxUser->customer_category_id = $defaultCustomerCategoryId;
        }

        $maxUser->save();

        return $this->mapper->toRecord($maxUser);
    }
}
