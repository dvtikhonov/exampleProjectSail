<?php

declare(strict_types=1);

namespace App\Support\Http;

use Illuminate\Http\Request;

/**
 * Разбор опциональных query-параметров HTTP-запроса.
 */
class QueryParamParser
{
    /**
     * Опциональный положительный int из query-параметра.
     */
    public function optionalPositiveInt(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue >= 1 ? $intValue : null;
    }

    /**
     * Опциональная обрезанная строка из query-параметра.
     */
    public function optionalTrimmedString(Request $request, string $key, int $maxLength): ?string
    {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $maxLength);
    }
}
