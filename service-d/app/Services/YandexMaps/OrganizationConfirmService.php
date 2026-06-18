<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\Contracts\OrganizationSyncDispatcherInterface;
use App\Contracts\ResolveSessionStoreInterface;
use App\DTO\YandexMaps\ConfirmOrganizationDto;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\Exceptions\Organization\InvalidOrganizationCandidateException;
use App\Models\Organization;
use App\Models\User;

/**
 * Подтверждение выбора организации после resolve-сессии.
 *
 * Берёт кандидата из кеша сессии, сохраняет/обновляет запись в БД и запускает синхронизацию отзывов.
 */
class OrganizationConfirmService
{
    public function __construct(
        private readonly ResolveSessionStoreInterface $sessionStore,
        private readonly OrganizationRepositoryInterface $organizationRepository,
        private readonly OrganizationSyncDispatcherInterface $syncDispatcher,
    ) {}

    /**
     * @throws InvalidOrganizationCandidateException если orgId не найден среди кандидатов сессии
     */
    public function confirm(User $user, ConfirmOrganizationDto $dto): Organization
    {
        $session = $this->sessionStore->get($dto->sessionId);

        $candidate = $this->findCandidate($session->candidates, $dto->orgId);

        $organization = $this->organizationRepository->upsertForUser(
            userId: $user->id,
            sourceUrl: $session->inputUrl,
            candidate: $candidate,
        );

        $this->syncDispatcher->dispatch($organization->id);

        return $organization->refresh();
    }

    /**
     * Ищет кандидата по yandex org id в списке, сохранённом в resolve-сессии.
     *
     * @param  OrganizationCandidateDto[]  $candidates
     */
    private function findCandidate(array $candidates, string $orgId): OrganizationCandidateDto
    {
        foreach ($candidates as $candidate) {
            if ($candidate->orgId === $orgId) {
                return $candidate;
            }
        }

        throw new InvalidOrganizationCandidateException;
    }
}
