<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Support;

/**
 * SSRF-защита origin PhotoText: exact match после rtrim `/` по allow-list.
 *
 * HTTP-правило и клиент PhotoText используют один и тот же нормализатор.
 */
final class SchedulePhotoOriginAllowList
{
    public const string REJECTION_MESSAGE = 'BASE_URL не входит в список разрешённых origin.';

    /**
     * @param  list<string>  $allowedOrigins
     */
    public function __construct(
        private readonly array $allowedOrigins,
    ) {}

    /**
     * Канонический origin из allow-list либо null, если значение небезопасно или не разрешено.
     */
    public function normalize(string $baseUrl): ?string
    {
        if (trim($baseUrl) === '') {
            return null;
        }

        $normalized = rtrim(trim($baseUrl), '/');
        $parts = parse_url($normalized);

        if (! is_array($parts)) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path !== '' && $path !== '/') {
            return null;
        }

        if (isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        foreach ($this->allowedOrigins as $origin) {
            if (trim($origin) === '') {
                continue;
            }

            $allowed = rtrim(trim($origin), '/');
            if (strcasecmp($allowed, $normalized) === 0) {
                return $allowed;
            }
        }

        return null;
    }
}
