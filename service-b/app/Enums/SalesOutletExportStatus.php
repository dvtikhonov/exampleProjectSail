<?php

namespace App\Enums;

enum SalesOutletExportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
