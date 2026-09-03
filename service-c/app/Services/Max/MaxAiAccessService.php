<?php

declare(strict_types=1);

namespace App\Services\Max;

use App\Contracts\Max\MaxAiAccessServiceInterface;
use App\Contracts\Max\MaxUserRepositoryInterface;
use App\DTO\Max\AiAccessStatusDto;
use App\DTO\Max\MaxUserIdentity;
use App\Exceptions\Food\FoodDomainException;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Управление доступом AI к базе для MAX mini-app.
 */
class MaxAiAccessService implements MaxAiAccessServiceInterface
{
    private const int AI_ACCESS_TTL_MINUTES = 30;

    public function __construct(
        private readonly MaxUserRepositoryInterface $maxUserRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getStatus(DateTimeInterface $now): AiAccessStatusDto
    {
        $this->maxUserRepository->clearExpiredAiAccess($now);

        $activeUser = $this->maxUserRepository->findActiveAiAccessUser($now);

        if ($activeUser === null) {
            return new AiAccessStatusDto(
                enabled: false,
                activeMaxUserId: null,
                expiresAt: null,
            );
        }

        return new AiAccessStatusDto(
            enabled: true,
            activeMaxUserId: $activeUser->maxUserId,
            expiresAt: $activeUser->aiAccessUntil,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toggle(MaxUserIdentity $currentUser, DateTimeInterface $now): AiAccessStatusDto
    {
        $this->maxUserRepository->clearExpiredAiAccess($now);

        $activeUser = $this->maxUserRepository->findActiveAiAccessUser($now);

        // Текущий пользователь активен → выключаем.
        if ($activeUser !== null && $activeUser->maxUserId === $currentUser->maxUserId) {
            $this->maxUserRepository->clearAiAccessForUserIfActive(
                maxUserId: $currentUser->maxUserId,
                now: $now,
            );

            return $this->getStatus($now);
        }

        // Иной активный пользователь существует → конфликт.
        if ($activeUser !== null) {
            throw new FoodDomainException('уже разрешен доступ AI к базе', 409);
        }

        $until = CarbonImmutable::instance($now)->addMinutes(self::AI_ACCESS_TTL_MINUTES);

        // Атомарное включение: обновляем только если на момент now нет активных записей.
        $updated = $this->maxUserRepository->setAiAccessUntilIfNoneActive(
            maxUserId: $currentUser->maxUserId,
            until: $until,
            now: $now,
        );

        if ($updated === 0) {
            // Включение проиграло конкуренту → активный доступ уже есть.
            throw new FoodDomainException('уже разрешен доступ AI к базе', 409);
        }

        return $this->getStatus($now);
    }
}
