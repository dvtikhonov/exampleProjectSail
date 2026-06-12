<?php

namespace App\DTO\SalesOutlets;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;

readonly class UpdateHeadOrganizationDto
{
    public function __construct(
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            headOrganization: trim($validated['head_organization']),
            headOrganizationType: HeadOrganizationType::fromLabelOrValue($validated['head_organization_type']),
        );
    }
}
