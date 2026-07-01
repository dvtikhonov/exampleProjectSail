<?php

declare(strict_types=1);

namespace App\Services\UrlShortener;

use App\Contracts\Repositories\ShortLinkRepositoryInterface;
use App\Contracts\UrlShortener\ShortCodeGeneratorInterface;
use App\Enums\ShortCodeLength;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Генерация уникального короткого кода [A-Za-z0-9].
 */
class ShortCodeGenerator implements ShortCodeGeneratorInterface
{
    private const int MAX_ATTEMPTS = 10;

    public function __construct(
        private readonly ShortLinkRepositoryInterface $shortLinkRepository,
    ) {}

    /**
     * @throws RuntimeException если не удалось сгенерировать уникальный код
     */
    public function generate(ShortCodeLength $length = ShortCodeLength::Default): string
    {
        $codeLength = $this->normalizeLength($length);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = Str::random($codeLength);

            if (! $this->shortLinkRepository->existsByCode($code)) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique short link code.');
    }

    /** Ограничивает длину кода диапазоном {@see ShortCodeLength::Min}–{@see ShortCodeLength::Max}. */
    private function normalizeLength(ShortCodeLength $length): int
    {
        $value = $length->value;

        return max(
            ShortCodeLength::Min->value,
            min($value, ShortCodeLength::Max->value),
        );
    }
}
