<?php

namespace App\Models;

use App\Enums\AsyncJobStatus;
use App\Enums\SalesOutletsReportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'uuid',
    'user_id',
    'report_type',
    'status',
    'filters',
    'file_path',
    'error_message',
])]
class SalesOutletReportJob extends Model
{
    protected function casts(): array
    {
        return [
            'report_type' => SalesOutletsReportType::class,
            'status' => AsyncJobStatus::class,
            'filters' => 'array',
        ];
    }
}
