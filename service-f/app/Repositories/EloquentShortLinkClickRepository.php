<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ShortLinkClickRepositoryInterface;
use App\Models\ShortLinkClick;
use DateTimeInterface;

/**
 * Eloquent-реализация журнала переходов по коротким ссылкам.
 */
class EloquentShortLinkClickRepository implements ShortLinkClickRepositoryInterface
{
    /** {@inheritDoc} */
    public function create(int $shortLinkId, string $ipAddress, ?DateTimeInterface $visitedAt = null): ShortLinkClick
    {
        return ShortLinkClick::query()->create([
            'short_link_id' => $shortLinkId,
            'ip_address' => $ipAddress,
            'visited_at' => $visitedAt ?? now(),
        ]);
    }
}
