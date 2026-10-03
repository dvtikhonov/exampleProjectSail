<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Результат LLM по одной source-линии (цены от LLM отбрасываются).
 */
readonly class MatchLineResultDto
{
    /**
     * @param  list<MatchCandidateDto>  $candidates
     */
    public function __construct(
        public string $lineKey,
        public string $displayName,
        public string $compareName,
        public array $candidates,
    ) {}

    /**
     * @param  array{line_key?: mixed, display_name?: mixed, compare_name?: mixed, candidates?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        $candidatesRaw = $data['candidates'] ?? [];
        $candidates = [];
        if (is_array($candidatesRaw)) {
            foreach ($candidatesRaw as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }
                $candidates[] = MatchCandidateDto::fromArray($candidate);
            }
        }

        return new self(
            lineKey: (string) ($data['line_key'] ?? ''),
            displayName: (string) ($data['display_name'] ?? ''),
            compareName: (string) ($data['compare_name'] ?? ''),
            candidates: $candidates,
        );
    }

    /**
     * @return array{
     *     line_key: string,
     *     display_name: string,
     *     compare_name: string,
     *     candidates: list<array{id: int, name: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'line_key' => $this->lineKey,
            'display_name' => $this->displayName,
            'compare_name' => $this->compareName,
            'candidates' => array_map(
                static fn (MatchCandidateDto $c): array => $c->toArray(),
                $this->candidates,
            ),
        ];
    }
}
