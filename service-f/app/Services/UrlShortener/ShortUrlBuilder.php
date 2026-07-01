<?php

declare(strict_types=1);

namespace App\Services\UrlShortener;

use App\Models\ShortLink;

/**
 * Сборка полного публичного URL короткой ссылки из APP_URL и кода.
 */
class ShortUrlBuilder
{
    public function build(ShortLink $shortLink): string
    {
        $baseUrl = request()->getSchemeAndHttpHost();

        if ($baseUrl === '') {
            $baseUrl = (string) config('app.url');
        }

        return rtrim($baseUrl, '/').'/'.$shortLink->code;
    }
}
