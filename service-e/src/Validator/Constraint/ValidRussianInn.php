<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/** Проверяет формат российского ИНН (10 или 12 цифр). */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class ValidRussianInn extends Constraint
{
    public string $message = 'Поле {{ attribute }} имеет неверный формат.';
}
