package ru.sail.salesoutlets.api.web;

import jakarta.validation.ConstraintViolation;
import jakarta.validation.ConstraintViolationException;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.validation.FieldError;
import org.springframework.web.bind.MethodArgumentNotValidException;
import org.springframework.web.bind.annotation.ExceptionHandler;
import org.springframework.web.bind.annotation.RestControllerAdvice;
import ru.sail.salesoutlets.domain.SalesOutletNotFoundException;

/**
 * Единый JSON-формат ошибок API (паритет с Laravel / service-e).
 */
@RestControllerAdvice
public class ApiExceptionHandler {

    public static final String VALIDATION_MESSAGE = "The given data was invalid.";

    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ResponseEntity<Map<String, Object>> handleMethodArgumentNotValid(MethodArgumentNotValidException ex) {
        Map<String, List<String>> errors = new LinkedHashMap<>();
        for (FieldError fieldError : ex.getBindingResult().getFieldErrors()) {
            String field = toSnakeCaseApiField(fieldError.getField());
            errors.computeIfAbsent(field, key -> new ArrayList<>())
                    .add(fieldError.getDefaultMessage() == null ? "Invalid." : fieldError.getDefaultMessage());
        }
        return validationResponse(errors);
    }

    /**
     * 422 для Bean Validation query index: ключи те же, что у {@link #toSnakeCaseApiField(String)}
     * ({@code status}, {@code sort}, {@code direction}, {@code columns.0}).
     */
    @ExceptionHandler(ConstraintViolationException.class)
    public ResponseEntity<Map<String, Object>> handleConstraintViolation(ConstraintViolationException ex) {
        Map<String, List<String>> errors = new LinkedHashMap<>();
        for (ConstraintViolation<?> violation : ex.getConstraintViolations()) {
            String field = toSnakeCaseApiField(violation.getPropertyPath().toString());
            errors.computeIfAbsent(field, key -> new ArrayList<>()).add(violation.getMessage());
        }
        return validationResponse(errors);
    }

    @ExceptionHandler(SalesOutletNotFoundException.class)
    public ResponseEntity<Map<String, String>> handleNotFound(SalesOutletNotFoundException ex) {
        return ResponseEntity.status(HttpStatus.NOT_FOUND)
                .body(Map.of("message", ex.getMessage()));
    }

    /**
     * Единое тело 422: {@code message} «The given data was invalid.» и карта {@code errors}.
     */
    public static ResponseEntity<Map<String, Object>> validationResponse(Map<String, List<String>> errors) {
        Map<String, Object> body = new LinkedHashMap<>();
        body.put("message", VALIDATION_MESSAGE);
        body.put("errors", errors);
        return ResponseEntity.status(HttpStatus.UNPROCESSABLE_ENTITY).body(body);
    }

    /**
     * Приводит имена полей Java/record к snake_case ключам API.
     */
    public static String toSnakeCaseApiField(String field) {
        if (field == null || field.isBlank()) {
            return "payload";
        }
        // columns[0] / columns[0].<list element> / columns.0 → columns.0 (Laravel parity)
        if (field.startsWith("columns[")) {
            int close = field.indexOf(']');
            if (close > "columns[".length()) {
                String index = field.substring("columns[".length(), close);
                return "columns." + index;
            }
        }
        if (field.startsWith("columns.")) {
            return field;
        }
        return switch (field) {
            case "headOrganization" -> "head_organization";
            case "headOrganizationType" -> "head_organization_type";
            case "organizationName" -> "organization_name";
            case "perPage" -> "per_page";
            case "columnFilters" -> "column_filters";
            default -> field;
        };
    }
}
