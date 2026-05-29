<?php

namespace App\Models;

use App\Enums\SalesOutletExportStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

#[Fillable([
    'uuid',
    'user_id',
    'status',
    'filters',
    'error_message',
])]
class SalesOutletMailJob extends Model
{
    protected static function booted(): void
    {
        static::saving(function (SalesOutletMailJob $mailJob): void {
            $mailJob->fillCurrentUserId();
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SalesOutletExportStatus::class,
            'filters' => 'array',
        ];
    }

    private function fillCurrentUserId(): void
    {
        if ($this->user_id !== null) {
            return;
        }

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
