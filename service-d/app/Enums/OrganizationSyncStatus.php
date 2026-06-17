<?php

namespace App\Enums;

enum OrganizationSyncStatus: string
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Completed = 'completed';
    case Failed = 'failed';
}
