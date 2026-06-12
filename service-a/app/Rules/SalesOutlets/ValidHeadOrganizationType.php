<?php

namespace App\Rules\SalesOutlets;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;

class ValidHeadOrganizationType implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (HeadOrganizationType::fromLabelOrValue((string) $value) === null) {
            $fail('Выбранное значение недопустимо.');
        }
    }
}
