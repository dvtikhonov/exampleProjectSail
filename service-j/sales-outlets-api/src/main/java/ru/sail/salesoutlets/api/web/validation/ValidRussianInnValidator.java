package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import ru.sail.salesoutlets.domain.validation.RussianInn;

/**
 * Валидатор {@link ValidRussianInn}.
 */
public class ValidRussianInnValidator implements ConstraintValidator<ValidRussianInn, String> {

    @Override
    public boolean isValid(String value, ConstraintValidatorContext context) {
        if (value == null || value.isBlank()) {
            return true;
        }
        if (RussianInn.isValid(value)) {
            return true;
        }
        context.disableDefaultConstraintViolation();
        context.buildConstraintViolationWithTemplate(
                context.getDefaultConstraintMessageTemplate()
                        .replace("{attribute}", "inn")
        ).addConstraintViolation();
        return false;
    }
}
