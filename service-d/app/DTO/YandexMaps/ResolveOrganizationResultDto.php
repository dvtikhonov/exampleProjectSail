<?php

declare(strict_types=1);

namespace App\DTO\YandexMaps;

readonly class ResolveOrganizationResultDto
{
    /**
     * @param  OrganizationCandidateDto[]  $candidates
     */
    public function __construct(
        public string $sessionId,
        public string $inputUrl,
        public string $searchText,
        public ?string $clarification,
        public string $resolvedUrl,
        public int $matchCount,
        public array $candidates,
        public bool $autoSelected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'input_url' => $this->inputUrl,
            'search_text' => $this->searchText,
            'clarification' => $this->clarification,
            'resolved_url' => $this->resolvedUrl,
            'match_count' => $this->matchCount,
            'candidates' => array_map(
                static fn (OrganizationCandidateDto $candidate): array => $candidate->toArray(),
                $this->candidates,
            ),
            'auto_selected' => $this->autoSelected,
        ];
    }
}
