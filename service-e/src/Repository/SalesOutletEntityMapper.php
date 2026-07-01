<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\SalesOutlet;
use App\Entity\SalesOutlet as SalesOutletEntity;

/** Маппинг Doctrine Entity → доменная модель SalesOutlet. */
final class SalesOutletEntityMapper
{
    /** Маппит Doctrine Entity в доменную модель. */
    public static function toDomain(SalesOutletEntity $entity): SalesOutlet
    {
        return new SalesOutlet(
            id: (int) $entity->getId(),
            shop: $entity->getShop(),
            manager: $entity->getManager(),
            curator: $entity->getCurator(),
            name: $entity->getName(),
            inn: $entity->getInn(),
            headOrganization: $entity->getHeadOrganization(),
            headOrganizationType: $entity->getHeadOrganizationType(),
            organizationName: $entity->getOrganizationName(),
            status: $entity->getStatus(),
            approved: $entity->getApproved(),
            userId: $entity->getUserId(),
        );
    }
}
