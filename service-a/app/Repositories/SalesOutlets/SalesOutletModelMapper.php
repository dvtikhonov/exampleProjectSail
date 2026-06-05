<?php

namespace App\Repositories\SalesOutlets;

use App\Domain\SalesOutlets\SalesOutlet;
use App\Models\SalesOutlet as SalesOutletModel;

final class SalesOutletModelMapper
{
    public static function toDomain(SalesOutletModel $model): SalesOutlet
    {
        return new SalesOutlet(
            id: $model->id,
            shop: $model->shop,
            manager: $model->manager,
            curator: $model->curator,
            name: $model->name,
            inn: $model->inn,
            headOrganization: $model->head_organization,
            headOrganizationType: $model->head_organization_type,
            organizationName: $model->organization_name,
            status: $model->status,
            approved: $model->approved,
            userId: $model->user_id,
        );
    }
}
