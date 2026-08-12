<?php

declare(strict_types=1);

namespace App\Support\Max;

use InvalidArgumentException;

/**
 * Диапазон max_user_id для нагрузочных VU (изолирован от демо-сидов 1001–1006).
 */
final class MaxLoadTestUserIds
{
    public const BASE_ID = 900_001;

    public const DEFAULT_COUNT = 30;

    public const TOKEN_FILE_NAME = 'load-test-tokens.json';

    /**
     * Возвращает список max_user_id: BASE_ID .. BASE_ID+count-1.
     *
     * @return list<int>
     */
    public static function range(int $count): array
    {
        if ($count < 1) {
            throw new InvalidArgumentException('count должен быть >= 1.');
        }

        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $ids[] = self::BASE_ID + $i;
        }

        return $ids;
    }

    /**
     * Путь к JSON с токенами по умолчанию (storage/app/load-test-tokens.json).
     */
    public static function defaultTokenFilePath(): string
    {
        return storage_path('app/'.self::TOKEN_FILE_NAME);
    }
}
