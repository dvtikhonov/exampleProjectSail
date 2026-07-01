<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;

/** DTO обновления головной организации торговой точки. */
readonly class UpdateHeadOrganizationDto
{
    public function __construct(
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
    ) {
    }

    /**
     * Собирает DTO из валидированных полей POST head-organization.
     *
     * @param array<string, mixed> $validated
     */
    public static function fromValidated(array $validated): self
    {
        $headOrganizationType = HeadOrganizationType::fromLabelOrValue((string) $validated['head_organization_type']);

        if (null === $headOrganizationType) {
            throw new \InvalidArgumentException('Invalid head organization type.');
        }

        return new self(
            headOrganization: trim((string) $validated['head_organization']),
            headOrganizationType: $headOrganizationType,
        );
    }
}
