package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;

/**
 * Валидатор {@link ValidHeadOrganizationType}.
 */
public class ValidHeadOrganizationTypeValidator
        implements ConstraintValidator<ValidHeadOrganizationType, String> {

    @Override
    public boolean isValid(String value, ConstraintValidatorContext context) {
        if (value == null || value.isBlank()) {
            return true;
        }
        return HeadOrganizationType.fromLabelOrValue(value).isPresent();
    }
}
