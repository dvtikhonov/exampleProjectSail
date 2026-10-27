package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.ConstraintValidator;
import jakarta.validation.ConstraintValidatorContext;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;

/**
 * Валидатор {@link InAllowedSalesOutletColumn}.
 */
public class InAllowedSalesOutletColumnValidator
        implements ConstraintValidator<InAllowedSalesOutletColumn, String> {

    private final SalesOutletsMetadataRepository metadataRepository;

    public InAllowedSalesOutletColumnValidator(SalesOutletsMetadataRepository metadataRepository) {
        this.metadataRepository = metadataRepository;
    }

    @Override
    public boolean isValid(String value, ConstraintValidatorContext context) {
        if (value == null || value.isBlank()) {
            return true;
        }
        return metadataRepository.allowedColumnKeys().contains(value);
    }
}
