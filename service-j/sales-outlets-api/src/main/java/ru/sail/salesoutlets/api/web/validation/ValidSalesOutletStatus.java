package ru.sail.salesoutlets.api.web.validation;

import jakarta.validation.Constraint;
import jakarta.validation.Payload;
import java.lang.annotation.Documented;
import java.lang.annotation.ElementType;
import java.lang.annotation.Retention;
import java.lang.annotation.RetentionPolicy;
import java.lang.annotation.Target;

/**
 * Bean Validation: значение статуса торговой точки ({@code approved}/{@code review}/{@code blocked}).
 */
@Documented
@Constraint(validatedBy = ValidSalesOutletStatusValidator.class)
@Target({ElementType.FIELD, ElementType.PARAMETER})
@Retention(RetentionPolicy.RUNTIME)
public @interface ValidSalesOutletStatus {

    String message() default "Выбранное значение недопустимо.";

    Class<?>[] groups() default {};

    Class<? extends Payload>[] payload() default {};
}
