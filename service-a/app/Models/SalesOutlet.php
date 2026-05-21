<?php

namespace App\Models;

use App\Enums\HeadOrganizationType;
use App\Enums\SalesOutletStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    private function fillCurrentUserId(): void
    {
        $userId = Auth::id() ?? $this->currentUserIdFromGatewayHeader();

        if ($userId === null) {
            return;
        }

        $this->forceFill([
            'user_id' => $userId,
        ]);
    }

    private function currentUserIdFromGatewayHeader(): ?int
    {
        $userId = request()->header('X-User-Id');

        if (! is_numeric($userId)) {
            return null;
        }

        return (int) $userId;
    }
}
