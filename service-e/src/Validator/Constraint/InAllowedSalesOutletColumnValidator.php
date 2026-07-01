<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** Валидатор ограничения InAllowedSalesOutletColumn. */
class InAllowedSalesOutletColumnValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
    ) {
    }

    /** {@inheritDoc} */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof InAllowedSalesOutletColumn) {
            throw new UnexpectedTypeException($constraint, InAllowedSalesOutletColumn::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!in_array((string) $value, $this->metadataRepository->allowedColumnKeys(), true)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
