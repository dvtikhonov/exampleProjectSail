<?php

declare(strict_types=1);

namespace App\Repositories\Max;

use App\Contracts\Max\MaxLoadTestUserRepositoryInterface;
use App\Contracts\Max\MaxUserAiAccessRepositoryInterface;
use App\Contracts\Max\MaxUserDeliveryRepositoryInterface;
use App\Contracts\Max\MaxUserIdentityRepositoryInterface;
use App\Contracts\Max\MaxUserManualOrderQueryRepositoryInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Max\MaxUserRecord;
use App\DTO\Max\MaxWebAppInitDataDto;
use App\DTO\Shared\PaginatedResultDto;
use DateTimeInterface;

/**
 * Composition-адаптер полного порта пользователей MAX:
 * делегирует в identity / delivery / AI / manual-order / load-test.
 */
class EloquentMaxUserRepository implements MaxUserRepositoryInterface
{
    public function __construct(
        private readonly MaxUserIdentityRepositoryInterface $identityRepository,
        private readonly MaxUserDeliveryRepositoryInterface $deliveryRepository,
        private readonly MaxUserAiAccessRepositoryInterface $aiAccessRepository,
        private readonly MaxUserManualOrderQueryRepositoryInterface $manualOrderQueryRepository,
        private readonly MaxLoadTestUserRepositoryInterface $loadTestUserRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function listMaxUserIdsWithDeliveryAddress(): array
    {
        return $this->deliveryRepository->listMaxUserIdsWithDeliveryAddress();
    }

    /**
     * {@inheritDoc}
     */
    public function findByMaxUserId(int $maxUserId): ?MaxUserRecord
    {
        return $this->identityRepository->findByMaxUserId($maxUserId);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertFromInitData(
        MaxWebAppInitDataDto $initData,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->identityRepository->upsertFromInitData($initData, $defaultCustomerCategoryId);
    }

    /**
     * {@inheritDoc}
     */
    public function upsertLoadTestUser(
        int $maxUserId,
        string $firstName,
        string $username,
        ?int $defaultCustomerCategoryId,
    ): MaxUserRecord {
        return $this->loadTestUserRepository->upsertLoadTestUser(
            $maxUserId,
            $firstName,
            $username,
            $defaultCustomerCategoryId,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function paginateForManualOrders(?string $query, int $perPage): PaginatedResultDto
    {
        return $this->manualOrderQueryRepository->paginateForManualOrders($query, $perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findByNameFieldsSubstring(string $query): array
    {
        return $this->manualOrderQueryRepository->findByNameFieldsSubstring($query);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDeliveryAddress(int $maxUserId, string $deliveryAddress): void
    {
        $this->deliveryRepository->updateDeliveryAddress($maxUserId, $deliveryAddress);
    }

    /**
     * {@inheritDoc}
     */
    public function clearExpiredAiAccess(DateTimeInterface $now): int
    {
        return $this->aiAccessRepository->clearExpiredAiAccess($now);
    }

    /**
     * {@inheritDoc}
     */
    public function findActiveAiAccessUser(DateTimeInterface $now): ?MaxUserRecord
    {
        return $this->aiAccessRepository->findActiveAiAccessUser($now);
    }

    /**
     * {@inheritDoc}
     */
    public function clearAiAccessForUserIfActive(int $maxUserId, DateTimeInterface $now): int
    {
        return $this->aiAccessRepository->clearAiAccessForUserIfActive($maxUserId, $now);
    }

    /**
     * {@inheritDoc}
     */
    public function setAiAccessUntilIfNoneActive(int $maxUserId, DateTimeInterface $until, DateTimeInterface $now): int
    {
        return $this->aiAccessRepository->setAiAccessUntilIfNoneActive($maxUserId, $until, $now);
    }
}
