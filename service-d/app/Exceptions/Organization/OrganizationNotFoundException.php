<?php

declare(strict_types=1);

namespace App\Exceptions\Organization;

use RuntimeException;

class OrganizationNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Организация не настроена.');
    }
}
