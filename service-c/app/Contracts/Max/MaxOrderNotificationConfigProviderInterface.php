<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxOrderNotificationConfig;

interface MaxOrderNotificationConfigProviderInterface
{
    public function config(): MaxOrderNotificationConfig;
}
