<?php

declare(strict_types=1);

namespace App\Contracts\Max;

use App\DTO\Max\MaxWebAppInitDataDto;

/**
 * Валидация и разбор initData MAX WebApp.
 */
interface MaxWebAppInitDataValidatorInterface
{
    /**
     * Проверяет подпись и срок действия initData.
     *
     * @throws \App\Exceptions\Max\MaxWebAppInitDataException
     */
    public function validate(string $initData): MaxWebAppInitDataDto;
}
