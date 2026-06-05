<?php

namespace App\DTO\SalesOutlets;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

readonly class UpdateSalesOutletDto
{
    public function __construct(
        public string $shop,
        public string $manager,
        public string $curator,
        public string $name,
        public string $inn,
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
        public string $organizationName,
        public SalesOutletStatus $status,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            shop: trim($validated['shop']),
            manager: trim($validated['manager']),
            curator: trim($validated['curator']),
            name: trim($validated['name']),
            inn: trim($validated['inn']),
            headOrganization: trim($validated['head_organization']),
            headOrganizationType: HeadOrganizationType::fromLabelOrValue($validated['head_organization_type']),
            organizationName: trim($validated['organization_name']),
            status: SalesOutletStatus::from($validated['status']),
        );
    }
}
