<?php

declare(strict_types=1);

namespace App\Contracts\UrlShortener;

use App\DTO\UrlShortener\CreateShortLinkDto;
use App\Exceptions\UrlShortener\ShortLinkNotFoundException;
use App\Models\ShortLink;

/**
 * Бизнес-логика URL shortener: создание, редирект и учёт переходов.
 */
interface UrlShortenerServiceInterface
{
    public function createShortLink(CreateShortLinkDto $dto): ShortLink;

    /**
     * @throws ShortLinkNotFoundException
     */
    public function resolveRedirect(string $code, string $ipAddress): ShortLink;

    /**
     * @throws ShortLinkNotFoundException если запись не найдена или принадлежит другому пользователю
     */
    public function deleteShortLink(int $shortLinkId, int $userId): void;
}
