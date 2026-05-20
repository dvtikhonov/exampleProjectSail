<?php

namespace App\Models;

use App\Enums\HeadOrganizationType;
use App\Enums\SalesOutletStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
])]
class SalesOutlet extends Model
{
    protected function casts(): array
    {
        return [
            'status' => SalesOutletStatus::class,
            'head_organization_type' => HeadOrganizationType::class,
        ];
    }
}
