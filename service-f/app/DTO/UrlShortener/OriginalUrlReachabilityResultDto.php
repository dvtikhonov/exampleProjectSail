<?php

declare(strict_types=1);

namespace App\DTO\UrlShortener;

/**
 * Результат HTTP-проверки исходного URL перед сохранением короткой ссылки.
 */
final readonly class OriginalUrlReachabilityResultDto
{
    public function __construct(
        public bool $isReachable,
        public ?int $httpStatusCode = null,
    ) {}

    /** true, если сервер ответил HTTP 200. */
    public function isOk(): bool
    {
        return $this->isReachable;
    }
}
