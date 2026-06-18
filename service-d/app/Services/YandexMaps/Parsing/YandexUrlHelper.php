<?php

declare(strict_types=1);

namespace App\Services\YandexMaps\Parsing;

/**
 * Разбор URL и числовых полей из данных Яндекс.Карт (org id, рейтинг, счётчики).
 */
class YandexUrlHelper
{
    private const YANDEX_MAPS_HOST_PATTERN = '/^yandex\.(ru|com|kz|com\.tr)$/i';

    private const ORG_URL_PATTERN = '/\/maps\/org\/[^\/]+\/(\d+)\/?/i';

    private const ORG_LINK_PATTERN = '/\/org\/[^\/]+\/(\d+)/i';

    /** Проверяет, что URL ведёт на домен yandex.* и путь содержит /maps. */
    public function isYandexMapsUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['host'], $parsed['path'])) {
            return false;
        }

        return preg_match(self::YANDEX_MAPS_HOST_PATTERN, (string) $parsed['host']) === 1
            && str_contains((string) $parsed['path'], '/maps');
    }

    /** Числовой id из полного URL вида .../maps/org/{slug}/{id}/. */
    public function extractOrgIdFromUrl(string $url): ?string
    {
        if (preg_match(self::ORG_URL_PATTERN, $url, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** Числовой id из относительного href вида /org/{slug}/{id}. */
    public function extractOrgIdFromHref(string $href): ?string
    {
        if (preg_match(self::ORG_LINK_PATTERN, $href, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** Канонический URL карточки организации с сохранением origin и slug из исходного URL. */
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

    /** true для URL прямой карточки организации (содержит /maps/org/.../id). */
    public function isDirectOrgUrl(string $url): bool
    {
        return preg_match(self::ORG_URL_PATTERN, $url) === 1;
    }

    /** scheme://host или https://yandex.ru по умолчанию. */
    public function safeOrigin(string $url): string
    {
        $parsed = parse_url($url);

        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return 'https://yandex.ru';
        }

        return $parsed['scheme'].'://'.$parsed['host'];
    }

    /** Нормализует рейтинг из числа или строки (запятая → точка, отсечение мусора). */
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

    /** Целое количество из числа или строки с пробелами/разделителями тысяч. */
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
