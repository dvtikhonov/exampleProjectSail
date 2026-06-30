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
        if (!$constraint instanceof ValidHeadOrganizationType) {
            throw new UnexpectedTypeException($constraint, ValidHeadOrganizationType::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (null === HeadOrganizationType::fromLabelOrValue((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
