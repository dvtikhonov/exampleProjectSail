<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxWebAppInitDataDto;
use App\Exceptions\Max\MaxWebAppInitDataException;

/**
 * Валидация и разбор initData MAX WebApp.
 */
interface MaxWebAppInitDataValidatorInterface
{
    /**
     * Проверяет подпись и срок действия initData.
     *
     * @throws MaxWebAppInitDataException
     */
    public function validate(string $initData): MaxWebAppInitDataDto;
}
