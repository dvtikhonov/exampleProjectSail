<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Порт доступа к текущему HTTP-запросу без инжекта Illuminate Request в конструктор.
 *
 * Нужен там, где зависимости могут кэшироваться между HTTP-вызовами (Route cache),
 * а user() должен читаться заново на каждый вызов.
 */
interface CurrentHttpRequestInterface
{
    /**
     * Возвращает аутентифицированного пользователя текущего запроса или null.
     */
    public function user(): mixed;
}
