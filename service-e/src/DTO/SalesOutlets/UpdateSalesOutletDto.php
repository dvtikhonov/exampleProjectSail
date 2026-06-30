<?php

declare(strict_types=1);

namespace App\DTO\SalesOutlets;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

/** DTO обновления полей торговой точки. */
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
    ) {
    }

    /**
     * @param array<string, mixed> $validated
     */
    public static function fromValidated(array $validated): self
    {
        $headOrganizationType = HeadOrganizationType::fromLabelOrValue((string) $validated['head_organization_type']);

        if (null === $headOrganizationType) {
            throw new \InvalidArgumentException('Invalid head organization type.');
        }

        return new self(
            shop: trim((string) $validated['shop']),
            manager: trim((string) $validated['manager']),
            curator: trim((string) $validated['curator']),
            name: trim((string) $validated['name']),
            inn: trim((string) $validated['inn']),
            headOrganization: trim((string) $validated['head_organization']),
            headOrganizationType: $headOrganizationType,
            organizationName: trim((string) $validated['organization_name']),
            status: SalesOutletStatus::from((string) $validated['status']),
        );
    }
}
