<?php

declare(strict_types=1);

namespace App\Services\UrlShortener;

use App\Contracts\Repositories\ShortLinkRepositoryInterface;
use App\Contracts\UrlShortener\ShortCodeGeneratorInterface;
use App\Contracts\UrlShortener\UrlShortenerServiceInterface;
use App\DTO\UrlShortener\CreateShortLinkDto;
use App\Exceptions\UrlShortener\ShortLinkNotFoundException;
use App\Models\ShortLink;

/**
 * Бизнес-логика URL shortener: создание, редирект и учёт переходов.
 */
class UrlShortenerService implements UrlShortenerServiceInterface
{
    public function __construct(
        private readonly ShortLinkRepositoryInterface $shortLinkRepository,
        private readonly ShortCodeGeneratorInterface $shortCodeGenerator,
    ) {}

    /** Генерирует уникальный код и сохраняет короткую ссылку. */
    public function createShortLink(CreateShortLinkDto $dto): ShortLink
    {
        $code = $this->shortCodeGenerator->generate();

        return $this->shortLinkRepository->create(
            userId: $dto->userId,
            originalUrl: $dto->originalUrl,
            code: $code,
        );
    }

    /**
     * Находит ссылку по коду, фиксирует переход и увеличивает счётчик кликов.
     *
     * @throws ShortLinkNotFoundException
     */
    public function resolveRedirect(string $code, string $ipAddress): ShortLink
    {
        $shortLink = $this->shortLinkRepository->findByCode($code);

        if ($shortLink === null) {
            throw new ShortLinkNotFoundException($code);
        }

        $this->shortLinkRepository->recordVisit($shortLink->id, $ipAddress);

        return $shortLink->refresh();
    }

    /**
     * Удаляет ссылку пользователя.
     *
     * @throws ShortLinkNotFoundException если запись не найдена или принадлежит другому пользователю
     */
    public function deleteShortLink(int $shortLinkId, int $userId): void
    {
        $deleted = $this->shortLinkRepository->deleteForUser($shortLinkId, $userId);

        if (! $deleted) {
            throw new ShortLinkNotFoundException((string) $shortLinkId);
        }
    }
}
