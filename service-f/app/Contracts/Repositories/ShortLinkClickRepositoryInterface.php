<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ShortLinkClick;
use DateTimeInterface;

/**
 * Запись переходов по коротким ссылкам.
 */
interface ShortLinkClickRepositoryInterface
{
    /** Фиксирует один переход по короткой ссылке (IP и время визита). */
    public function create(int $shortLinkId, string $ipAddress, ?DateTimeInterface $visitedAt = null): ShortLinkClick;
}
