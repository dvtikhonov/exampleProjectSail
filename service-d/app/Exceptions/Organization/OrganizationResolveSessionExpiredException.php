<?php

declare(strict_types=1);

namespace App\Exceptions\Organization;

use RuntimeException;

class OrganizationResolveSessionExpiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Сессия поиска организации истекла. Повторите поиск по ссылке.');
    }
}
