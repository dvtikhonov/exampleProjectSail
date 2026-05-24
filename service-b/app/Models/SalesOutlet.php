<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

#[Fillable([
    'shop',
    'manager',
    'curator',
    'name',
    'inn',
    'head_organization',
    'head_organization_type',
    'organization_name',
    'status',
    'approved',
    'user_id',
])]
class SalesOutlet extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => SalesOutletStatus::class,
            'head_organization_type' => HeadOrganizationType::class,
        ];
    }
}
