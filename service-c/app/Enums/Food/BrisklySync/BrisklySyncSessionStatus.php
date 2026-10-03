<?php

declare(strict_types=1);

namespace App\Enums\Food\BrisklySync;

/**
 * Статус жизненного цикла сессии Briskly sync.
 */
enum BrisklySyncSessionStatus: string
{
    case Setup = 'setup';
    case Matched = 'matched';
    case Approved = 'approved';
    case Applied = 'applied';
    case Failed = 'failed';
}
