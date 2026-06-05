<?php

namespace App\Rules\SalesOutlets;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidRussianInn implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match('/^\d{10}(\d{2})?$/', (string) $value)) {
            $fail('Поле :attribute имеет неверный формат.');
        }
    }
}
