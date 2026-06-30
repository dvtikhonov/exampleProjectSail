<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Domain\SalesOutlet;
use Shared\SalesOutletsDomain\DTO\SalesOutletRowDto;

/** Преобразует доменную модель в DTO строки таблицы для API-ответа. */
final class SalesOutletRowPresenter
{
    public static function fromDomain(SalesOutlet $salesOutlet): SalesOutletRowDto
    {
        return new SalesOutletRowDto(
            id: $salesOutlet->id,
            shop: $salesOutlet->shop,
            manager: $salesOutlet->manager,
            curator: $salesOutlet->curator,
            name: $salesOutlet->name,
            inn: $salesOutlet->inn,
            headOrganization: $salesOutlet->headOrganization,
            headOrganizationType: $salesOutlet->headOrganizationType->value,
            headOrganizationTypeLabel: $salesOutlet->headOrganizationType->label(),
            organizationName: $salesOutlet->organizationName,
            status: $salesOutlet->status->value,
            statusLabel: $salesOutlet->status->label(),
            approved: $salesOutlet->approved,
            userId: $salesOutlet->userId,
            rowTone: $salesOutlet->status->rowTone(),
        );
    }
}
