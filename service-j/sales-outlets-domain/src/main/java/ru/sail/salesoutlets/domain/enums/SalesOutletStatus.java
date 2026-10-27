package ru.sail.salesoutlets.domain.enums;

import java.util.Arrays;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import ru.sail.salesoutlets.domain.dto.LabelValue;

/**
 * Статус торговой точки (значения колонки {@code status}).
 */
public enum SalesOutletStatus {
    APPROVED("approved", "Одобрено", "success"),
    REVIEW("review", "На проверке", "warning"),
    BLOCKED("blocked", "Есть изменения", "danger");

    private final String value;
    private final String label;
    private final String rowTone;

    SalesOutletStatus(String value, String label, String rowTone) {
        this.value = value;
        this.label = label;
        this.rowTone = rowTone;
    }

    public String value() {
        return value;
    }

    public String label() {
        return label;
    }

    public String rowTone() {
        return rowTone;
    }

    /**
     * @return опции для UI-фильтра статуса
     */
    public static List<LabelValue> options() {
        return Arrays.stream(values())
                .map(status -> new LabelValue(status.value, status.label))
                .toList();
    }

    /**
     * Значения {@link #value()} статусов, чья {@link #label()} содержит подстроку.
     * Сравнение без учёта регистра. Пустая, blank и {@code null} строка дают пустой список.
     *
     * @param labelSubstring фрагмент человекочитаемой метки
     * @return значения совпавших статусов в порядке объявления
     */
    public static List<String> valuesByLabelSubstring(String labelSubstring) {
        if (labelSubstring == null || labelSubstring.isBlank()) {
            return List.of();
        }
        String needle = labelSubstring.toLowerCase(Locale.ROOT);
        return Arrays.stream(values())
                .filter(status -> status.label().toLowerCase(Locale.ROOT).contains(needle))
                .map(SalesOutletStatus::value)
                .toList();
    }

    /**
     * Разбирает строковое значение статуса (точное совпадение с {@link #value()}).
     */
    public static Optional<SalesOutletStatus> tryFrom(String raw) {
        if (raw == null || raw.isBlank()) {
            return Optional.empty();
        }
        String normalized = raw.trim().toLowerCase(Locale.ROOT);
        return Arrays.stream(values())
                .filter(status -> status.value.equals(normalized))
                .findFirst();
    }

    /**
     * @throws IllegalArgumentException если значение неизвестно
     */
    public static SalesOutletStatus fromValue(String raw) {
        return tryFrom(raw)
                .orElseThrow(() -> new IllegalArgumentException("Unknown sales outlet status: " + raw));
    }
}
