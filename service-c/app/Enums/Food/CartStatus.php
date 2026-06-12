<?php

declare(strict_types=1);

namespace App\Enums\Food;

enum CartStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
}
