<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** Валидатор ограничения ValidHeadOrganizationType. */
class ValidHeadOrganizationTypeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof ValidHeadOrganizationType) {
            throw new UnexpectedTypeException($constraint, ValidHeadOrganizationType::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (HeadOrganizationType::fromLabelOrValue((string) $value) === null) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
