<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус выполнения задачи.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
}
