<?php

namespace App\Rules\SalesOutlets;

use App\Contracts\Repositories\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class InAllowedSalesOutletColumn implements ValidationRule
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! in_array((string) $value, $this->metadataRepository->allowedColumnKeys(), true)) {
            $fail('Выбранное значение недопустимо.');
        }
    }
}
