<?php

declare(strict_types=1);

namespace App\Contracts;

use App\DTO\YandexMaps\OrganizationCandidateDto;
use App\DTO\YandexMaps\ParserCollectResultDto;

interface OrganizationCandidateBuilderInterface
{
    /**
     * @return OrganizationCandidateDto[]
     */
    public function build(ParserCollectResultDto $collect): array;
}
