<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxLoadTestUserRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\Models\Max\MaxUser;

/**
 * Eloquent-реализация load-test порта пользователей MAX.
 */
class EloquentMaxLoadTestUserRepository implements MaxLoadTestUserRepositoryInterface
{
    public function __construct(
        private readonly MaxUserMapper $mapper,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function upsertLoadTestUser(
        int $maxUserId,
        string $firstName,
        string $username,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        $maxUser = MaxUser::query()->firstOrNew(['max_user_id' => $maxUserId]);

        if (! $maxUser->exists) {
            $maxUser->fill([
                'first_name' => $firstName,
                'username' => $username,
                'language_code' => 'ru',
            ]);
        }

        if ($maxUser->customer_category_id === null && $defaultCustomerCategoryId !== null) {
            $maxUser->customer_category_id = $defaultCustomerCategoryId;
        }

        $maxUser->save();

        return $this->mapper->toRecord($maxUser);
    }
}
