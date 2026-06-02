<?php

namespace App\Events;

/**
 * Domain event: report job row was created or its status (or related fields) changed.
 * Listeners may refresh derived views (e.g. aggregated stats broadcast).
 */
final readonly class SalesOutletReportJobMutated
{
    public function __construct(
        public string $uuid,
    ) {}
}
