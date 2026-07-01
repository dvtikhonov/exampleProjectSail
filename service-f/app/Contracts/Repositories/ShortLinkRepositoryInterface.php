<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\ShortLink;

/**
 * Доступ к коротким ссылкам (Eloquent).
 */
interface ShortLinkRepositoryInterface
{
    /** Находит короткую ссылку по публичному коду. */
    public function findByCode(string $code): ?ShortLink;

    /** Находит ссылку по id, принадлежащую указанному пользователю. */
    public function findByIdForUser(int $shortLinkId, int $userId): ?ShortLink;

    /** Создаёт запись короткой ссылки с нулевым счётчиком переходов. */
    public function create(int $userId, string $originalUrl, string $code): ShortLink;

    /** Увеличивает агрегированный счётчик кликов на 1. */
    public function incrementClicksCount(int $shortLinkId): void;

    /** Фиксирует переход (журнал + счётчик) в одной транзакции. */
    public function recordVisit(int $shortLinkId, string $ipAddress): void;

    /** Удаляет ссылку пользователя; возвращает false, если запись не найдена. */
    public function deleteForUser(int $shortLinkId, int $userId): bool;

    /** Проверяет занятость кода (для генератора уникальных значений). */
    public function existsByCode(string $code): bool;
}
