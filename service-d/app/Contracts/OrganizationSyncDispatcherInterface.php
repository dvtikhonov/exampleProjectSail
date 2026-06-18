<?php

declare(strict_types=1);

namespace App\Contracts;

interface OrganizationSyncDispatcherInterface
{
    public function dispatch(int $organizationId): void;
}
