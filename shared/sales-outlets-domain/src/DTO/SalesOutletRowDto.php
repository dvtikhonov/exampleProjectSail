<?php

namespace Shared\SalesOutletsDomain\DTO;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

readonly class SalesOutletRowDto
{
    public function __construct(
        public int $id,
        public string $shop,
        public string $manager,
        public string $curator,
        public string $name,
        public string $inn,
        public string $headOrganization,
        public string $headOrganizationType,
        public string $headOrganizationTypeLabel,
        public string $organizationName,
        public string $status,
        public string $statusLabel,
        public string $approved,
        public ?int $userId,
        public ?string $rowTone = null,
    ) {}

    public static function fromModel(object $salesOutlet): self
    {
        /** @var HeadOrganizationType $headOrganizationType */
        $headOrganizationType = $salesOutlet->head_organization_type;
        /** @var SalesOutletStatus $status */
        $status = $salesOutlet->status;

        return new self(
            id: $salesOutlet->id,
            shop: $salesOutlet->shop,
            manager: $salesOutlet->manager,
            curator: $salesOutlet->curator,
            name: $salesOutlet->name,
            inn: $salesOutlet->inn,
            headOrganization: $salesOutlet->head_organization,
            headOrganizationType: $headOrganizationType->value,
            headOrganizationTypeLabel: $headOrganizationType->label(),
            organizationName: $salesOutlet->organization_name,
            status: $status->value,
            statusLabel: $status->label(),
            approved: $salesOutlet->approved,
            userId: $salesOutlet->user_id,
            rowTone: $status->rowTone(),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(bool $includeRowTone = true): array
    {
        $row = [
            'id' => $this->id,
            'shop' => $this->shop,
            'manager' => $this->manager,
            'curator' => $this->curator,
            'name' => $this->name,
            'inn' => $this->inn,
            'head_organization' => $this->headOrganization,
            'head_organization_type' => $this->headOrganizationType,
            'head_organization_type_label' => $this->headOrganizationTypeLabel,
            'organization_name' => $this->organizationName,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'approved' => $this->approved,
            'user_id' => $this->userId,
        ];

        if ($includeRowTone) {
            $row['row_tone'] = $this->rowTone;
        }

        return $row;
    }
}
