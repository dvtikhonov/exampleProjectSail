<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/** Проверяет, что значение — ключ разрешённой колонки таблицы. */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class InAllowedSalesOutletColumn extends Constraint
{
    public string $message = 'Выбранное значение недопустимо.';
}
