<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxWebAppInitDataDto;

interface MaxWebAppInitDataValidatorInterface
{
    public function validate(string $initData): MaxWebAppInitDataDto;
}
