<?php

declare(strict_types=1);

namespace App\Validator\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/** Валидатор ограничения ValidRussianInn. */
class ValidRussianInnValidator extends ConstraintValidator
{
    /** {@inheritDoc} */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidRussianInn) {
            throw new UnexpectedTypeException($constraint, ValidRussianInn::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!preg_match('/^\d{10}(\d{2})?$/', (string) $value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ attribute }}', $this->formatAttributeLabel())
                ->addViolation();
        }
    }

    private function formatAttributeLabel(): string
    {
        $attribute = (string) $this->context->getPropertyPath();

        return '' !== $attribute ? $attribute : 'attribute';
    }
}
