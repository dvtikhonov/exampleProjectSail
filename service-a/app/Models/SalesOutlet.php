<?php

namespace App\Models;

use App\Enums\HeadOrganizationType;
use App\Enums\SalesOutletStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    protected static function booted(): void
    {
        static::saving(function (SalesOutlet $salesOutlet): void {
            $salesOutlet->fillCurrentUserId();
        });

        static::deleting(function (SalesOutlet $salesOutlet): void {
            if ($salesOutlet->isForceDeleting()) {
                return;
            }

            $salesOutlet->fillCurrentUserId();
            $salesOutlet->saveQuietly();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOutletStatus::class,
            'head_organization_type' => HeadOrganizationType::class,
        ];
    }

    private function fillCurrentUserId(): void
    {
        $userId = $this->currentUserIdFromGatewayHeader();

        if ($userId === null) {
            return;
        }

        $this->forceFill([
            'user_id' => $userId,
        ]);
    }

    private function currentUserIdFromGatewayHeader(): ?int
    {
        $userId = request()->attributes->get('gateway_user_id');

        if (! is_int($userId)) {
            return null;
        }

        return $userId;
    }
}
