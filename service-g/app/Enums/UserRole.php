<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Роль пользователя в приложении To-Do List.
 */
enum UserRole: string
{
    case User = 'user';
    case Admin = 'admin';
}
