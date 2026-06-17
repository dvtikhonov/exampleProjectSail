<?php

declare(strict_types=1);

namespace App\Services\YandexMaps;

use App\Contracts\OrganizationRepositoryInterface;
use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ConfirmOrganizationDto;
use App\Exceptions\Organization\InvalidOrganizationCandidateException;
use App\Jobs\SyncYandexOrganizationReviewsJob;
use App\Models\Organization;
use App\Models\User;

class OrganizationConfirmService
{
    public function __construct(
        private readonly OrganizationResolveService $resolveService,
        private readonly OrganizationRepositoryInterface $organizationRepository,
    ) {}

    public function confirm(User $user, ConfirmOrganizationDto $dto): Organization
    {
        $session = $this->resolveService->getSession($dto->sessionId);

        $candidate = $this->findCandidate($session['candidates'], $dto->orgId);

        $organization = $this->organizationRepository->upsertForUser(
            userId: $user->id,
            sourceUrl: $session['input_url'],
            candidate: $candidate,
        );

        SyncYandexOrganizationReviewsJob::dispatch($organization->id);

        return $organization->refresh();
    }

    /**
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
