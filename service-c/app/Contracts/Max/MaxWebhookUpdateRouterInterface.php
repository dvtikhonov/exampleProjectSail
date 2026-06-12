<?php

declare(strict_types=1);

namespace App\Contracts\Max;

interface MaxWebhookUpdateRouterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void;
}
