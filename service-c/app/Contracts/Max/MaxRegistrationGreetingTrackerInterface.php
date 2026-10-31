<?php

declare(strict_types=1);

namespace App\Contracts\Max;

/**
 * Пометка, что пользователю MAX уже отправляли приветствие с кнопкой mini-app.
 */
interface MaxRegistrationGreetingTrackerInterface
{
    /**
     * Приветствие этому user_id уже уходило.
     */
    public function wasSent(int $userId): bool;

    /**
     * Запоминает успешную отправку приветствия.
     */
    public function markSent(int $userId): void;

    /**
     * Снимает пометку, если отправка не удалась и приветствие можно повторить.
     */
    public function forget(int $userId): void;
}
