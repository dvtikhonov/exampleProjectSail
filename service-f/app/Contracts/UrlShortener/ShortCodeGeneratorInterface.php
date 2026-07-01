<?php

declare(strict_types=1);

namespace App\Contracts\UrlShortener;

use App\Enums\ShortCodeLength;

/**
 * Генерация уникального короткого кода [A-Za-z0-9].
 */
interface ShortCodeGeneratorInterface
{
    /**
     * @throws \RuntimeException если не удалось сгенерировать уникальный код
     */
    public function generate(ShortCodeLength $length = ShortCodeLength::Default): string;
}
