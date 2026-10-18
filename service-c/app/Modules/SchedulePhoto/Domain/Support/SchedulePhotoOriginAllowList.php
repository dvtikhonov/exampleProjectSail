<?php

declare(strict_types=1);

namespace App\Modules\SchedulePhoto\Domain\Support;

/**
 * SSRF-защита origin PhotoText: exact match после rtrim `/` по allow-list.
 *
 * HTTP-правило и клиент PhotoText используют один и тот же нормализатор.
 * localhost / 127.0.0.1 / host.docker.internal допускаются через allow-list;
 * metadata / link-local (169.254.0.0/16, fe80::/10) отклоняются всегда.
 */
final class SchedulePhotoOriginAllowList
{
    public const string REJECTION_MESSAGE = 'BASE_URL не входит в список разрешённых origin.';

    /** @var list<string> */
    private const array BLOCKED_METADATA_HOSTNAMES = [
        'metadata.google.internal',
        'metadata',
    ];

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

        if ($this->isBlockedMetadataOrLinkLocalHost($host)) {
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

    /**
     * Hard-reject cloud metadata / link-local даже если origin ошибочно попал в env allow-list.
     * Не трогает localhost / 127.0.0.1 / host.docker.internal.
     */
    private function isBlockedMetadataOrLinkLocalHost(string $host): bool
    {
        $host = trim($host, '[]');

        if (in_array($host, self::BLOCKED_METADATA_HOSTNAMES, true)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $this->isLinkLocalIpv4($host);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return $this->isLinkLocalIpv6($host);
        }

        return false;
    }

    private function isLinkLocalIpv4(string $ip): bool
    {
        // 169.254.0.0/16 (AWS/GCP/Azure IMDS и APIPA); без ip2long — signed 32-bit.
        return str_starts_with($ip, '169.254.');
    }

    private function isLinkLocalIpv6(string $ip): bool
    {
        // fe80::/10
        $packed = inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return false;
        }

        $firstByte = ord($packed[0]);
        $secondByte = ord($packed[1]);

        return $firstByte === 0xFE && ($secondByte & 0xC0) === 0x80;
    }
}
