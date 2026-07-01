<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ShortLinkClickRepositoryInterface;
use App\Contracts\Repositories\ShortLinkRepositoryInterface;
use App\Models\ShortLink;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-реализация доступа к коротким ссылкам.
 */
class EloquentShortLinkRepository implements ShortLinkRepositoryInterface
{
    public function __construct(
        private readonly ShortLinkClickRepositoryInterface $shortLinkClickRepository,
    ) {}
    /** {@inheritDoc} */
    public function findByCode(string $code): ?ShortLink
    {
        return ShortLink::query()
            ->where('code', $code)
            ->first();
    }

    /** {@inheritDoc} */
    public function findByIdForUser(int $shortLinkId, int $userId): ?ShortLink
    {
        return ShortLink::query()
            ->whereKey($shortLinkId)
            ->where('user_id', $userId)
            ->first();
    }

    /** {@inheritDoc} */
    public function create(int $userId, string $originalUrl, string $code): ShortLink
    {
        return ShortLink::query()->create([
            'user_id' => $userId,
            'original_url' => $originalUrl,
            'code' => $code,
            'clicks_count' => 0,
        ]);
    }

    /** {@inheritDoc} */
    public function incrementClicksCount(int $shortLinkId): void
    {
        ShortLink::query()
            ->whereKey($shortLinkId)
            ->increment('clicks_count');
    }

    /** {@inheritDoc} */
    public function recordVisit(int $shortLinkId, string $ipAddress): void
    {
        DB::transaction(function () use ($shortLinkId, $ipAddress): void {
            $this->shortLinkClickRepository->create(
                shortLinkId: $shortLinkId,
                ipAddress: $ipAddress,
            );

            $this->incrementClicksCount($shortLinkId);
        });
    }

    /** {@inheritDoc} */
    public function deleteForUser(int $shortLinkId, int $userId): bool
    {
        return ShortLink::query()
            ->whereKey($shortLinkId)
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    /** {@inheritDoc} */
    public function existsByCode(string $code): bool
    {
        return ShortLink::query()
            ->where('code', $code)
            ->exists();
    }
}
