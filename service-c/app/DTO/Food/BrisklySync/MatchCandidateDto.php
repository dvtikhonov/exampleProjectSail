<?php

declare(strict_types=1);

namespace App\DTO\Food\BrisklySync;

/**
 * Кандидат Briskly из ответа LLM (без price).
 */
readonly class MatchCandidateDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @param  array{id?: mixed, name?: mixed}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
        );
    }

    /**
     * @return array{id: int, name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
