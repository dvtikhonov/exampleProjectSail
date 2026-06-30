<?php

declare(strict_types=1);

namespace App\Domain;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

/**
 * Доменная модель торговой точки (readonly, без зависимости от Doctrine).
 */
readonly class SalesOutlet
{
    public function __construct(
        public int $id,
        public string $shop,
        public string $manager,
        public string $curator,
        public string $name,
        public string $inn,
        public string $headOrganization,
        public HeadOrganizationType $headOrganizationType,
        public string $organizationName,
        public SalesOutletStatus $status,
        public string $approved,
        public ?int $userId,
    ) {}
}
