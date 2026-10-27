package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.Constraint;
import jakarta.validation.Payload;
import java.lang.annotation.Documented;
import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

/**
 * Bean Validation: российский ИНН (10 или 12 цифр).
 */
@Documented
@Constraint(validatedBy = ValidRussianInnValidator.class)
@Target({ElementType.FIELD, ElementType.PARAMETER})
@Retention(RetentionPolicy.RUNTIME)
public @interface ValidRussianInn {

    String message() default "Поле {attribute} имеет неверный формат.";

    Class<?>[] groups() default {};

    Class<? extends Payload>[] payload() default {};
}
