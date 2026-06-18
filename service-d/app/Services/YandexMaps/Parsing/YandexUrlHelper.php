<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

/**
 * URL parsing and numeric extraction helpers for Yandex Maps organization data.
 */
class YandexUrlHelper
{
    private const YANDEX_MAPS_HOST_PATTERN = '/^yandex\.(ru|com|kz|com\.tr)$/i';

    private const ORG_URL_PATTERN = '/\/maps\/org\/[^\/]+\/(\d+)\/?/i';

    private const ORG_LINK_PATTERN = '/\/org\/[^\/]+\/(\d+)/i';

    public function isYandexMapsUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'], $parsed['path'])) {
            return false;
        }

        return preg_match(self::YANDEX_MAPS_HOST_PATTERN, (string) $parsed['host']) === 1
            && str_contains((string) $parsed['path'], '/maps');
    }

    public function extractOrgIdFromUrl(string $url): ?string
    {
        if (preg_match(self::ORG_URL_PATTERN, $url, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function extractOrgIdFromHref(string $href): ?string
    {
        if (preg_match(self::ORG_LINK_PATTERN, $href, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public function normalizeOrgUrl(string $url, string $orgId, string $slug = 'organization'): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return "https://yandex.ru/maps/org/{$slug}/{$orgId}/";
        }

        $path = (string) ($parsed['path'] ?? '');
        $resolvedSlug = $slug;

        if (preg_match('/\/maps\/org\/([^\/]+)\/\d+/i', $path, $matches) === 1) {
            $resolvedSlug = $matches[1];
        }

        $origin = $parsed['scheme'].'://'.$parsed['host'];

        return "{$origin}/maps/org/{$resolvedSlug}/{$orgId}/";
    }

    public function isDirectOrgUrl(string $url): bool
    {
        return preg_match(self::ORG_URL_PATTERN, $url) === 1;
    }

    public function safeOrigin(string $url): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return 'https://yandex.ru';
        }

        return $parsed['scheme'].'://'.$parsed['host'];
    }

    public function parseRating(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (float) $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/[^\d.]/', '', str_replace(',', '.', $value)) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        $parsed = (float) $normalized;

        return is_finite($parsed) ? $parsed : null;
    }

    public function parseCount(mixed $value): ?int
    {
        if (is_int($value) || is_float($value)) {
            return is_finite((float) $value) ? (int) floor((float) $value) : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $normalized = preg_replace('/\s/u', '', $value) ?? '';

        if (preg_match('/\d+/', $normalized, $matches) !== 1) {
            return null;
        }

        return (int) $matches[0];
    }
}
