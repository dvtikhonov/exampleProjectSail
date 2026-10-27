package ru.sail.salesoutlets.domain.validation;

import java.util.regex.Pattern;

/**
 * Проверка формата российского ИНН (10 или 12 цифр).
 */
public final class RussianInn {

    private static final Pattern PATTERN = Pattern.compile("^\\d{10}(\\d{2})?$");

    private RussianInn() {
    }

    /**
     * Пустая строка / null считаются невалидными на уровне домена;
     * HTTP-слой может пропускать null до Bean Validation {@code @NotBlank}.
     */
    public static boolean isValid(String value) {
        if (value == null || value.isBlank()) {
            return false;
        }
        return PATTERN.matcher(value.trim()).matches();
    }
}
