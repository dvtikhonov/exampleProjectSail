<?php

declare(strict_types=1);

namespace App\Contracts\UrlShortener;

use App\DTO\UrlShortener\OriginalUrlReachabilityResultDto;

/**
 * Проверка доступности исходного URL (ожидается HTTP 200).
 */
interface OriginalUrlReachabilityCheckerInterface
{
    public function check(string $url): OriginalUrlReachabilityResultDto;
}
