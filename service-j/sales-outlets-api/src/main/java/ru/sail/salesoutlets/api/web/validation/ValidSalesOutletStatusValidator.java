package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

/**
 * Валидатор {@link ValidSalesOutletStatus}.
 */
public class ValidSalesOutletStatusValidator implements ConstraintValidator<ValidSalesOutletStatus, String> {

    @Override
    public boolean isValid(String value, ConstraintValidatorContext context) {
        if (value == null || value.isBlank()) {
            return true;
        }
        return SalesOutletStatus.tryFrom(value).isPresent();
    }
}
