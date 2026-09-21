<?php

declare(strict_types=1);

namespace App\Support\Database;

/**
 * Экранирование пользовательского ввода для SQL LIKE.
 */
final class LikeEscape
{
    /**
     * Символ ESCAPE для MySQL LIKE (обратный слэш).
     */
    public const ESCAPE_CHAR = '\\';

    /**
     * Возвращает паттерн `%value%` с экранированием `\`, `%` и `_`.
     */
    public static function contains(string $value): string
    {
        $escaped = str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );

        return '%'.$escaped.'%';
    }
}
