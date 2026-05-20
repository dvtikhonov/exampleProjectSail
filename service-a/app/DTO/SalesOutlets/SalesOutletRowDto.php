<?php

namespace App\DTO\SalesOutlets;

use App\Models\SalesOutlet;

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
        public string $rowTone,
    ) {}

    public static function fromModel(SalesOutlet $salesOutlet): self
    {
        return new self(
            id: $salesOutlet->id,
            shop: $salesOutlet->shop,
            manager: $salesOutlet->manager,
            curator: $salesOutlet->curator,
            name: $salesOutlet->name,
            inn: $salesOutlet->inn,
            headOrganization: $salesOutlet->head_organization,
            headOrganizationType: $salesOutlet->head_organization_type->value,
            headOrganizationTypeLabel: $salesOutlet->head_organization_type->label(),
            organizationName: $salesOutlet->organization_name,
            status: $salesOutlet->status->value,
            statusLabel: $salesOutlet->status->label(),
            approved: $salesOutlet->approved,
            rowTone: $salesOutlet->status->rowTone(),
        );
    }

    /**
     * @return array<string, int|string>
     */
    public function toArray(): array
    {
        return [
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
            'row_tone' => $this->rowTone,
        ];
    }
}
