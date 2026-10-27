package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.Constraint;
import jakarta.validation.Payload;
import java.lang.annotation.Documented;
import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

/**
 * Bean Validation: допустимый вид головной организации (код или метка).
 */
@Documented
@Constraint(validatedBy = ValidHeadOrganizationTypeValidator.class)
@Target({ElementType.FIELD, ElementType.PARAMETER})
@Retention(RetentionPolicy.RUNTIME)
public @interface ValidHeadOrganizationType {

    String message() default "Выбранное значение недопустимо.";

    Class<?>[] groups() default {};

    Class<? extends Payload>[] payload() default {};
}
