<?php

declare(strict_types=1);

namespace App\Exceptions\Organization;

use RuntimeException;

class InvalidOrganizationCandidateException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Выбранная организация не найдена среди результатов поиска.');
    }
}
