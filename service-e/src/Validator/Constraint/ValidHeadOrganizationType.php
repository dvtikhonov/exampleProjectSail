<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/** Проверяет допустимый тип головной организации (enum HeadOrganizationType). */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ValidHeadOrganizationType extends Constraint
{
    public string $message = 'Выбранное значение недопустимо.';
}
