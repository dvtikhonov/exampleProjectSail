package ru.sail.salesoutlets.domain.enums;

import java.util.Arrays;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import ru.sail.salesoutlets.domain.dto.LabelValue;

/**
 * Вид головной организации (значения колонки {@code head_organization_type}).
 */
public enum HeadOrganizationType {
    INDIVIDUAL_ENTREPRENEUR("ip", "ИП"),
    LIMITED_LIABILITY_COMPANY("ooo", "ООО"),
    JOINT_STOCK_COMPANY("ao", "АО"),
    AGRICULTURAL_COOPERATIVE("spk", "СПК");

    private final String value;
    private final String label;

    HeadOrganizationType(String value, String label) {
        this.value = value;
        this.label = label;
    }

    public String value() {
        return value;
    }

    public String label() {
        return label;
    }

    /**
     * Разбирает код или русскую метку (без учёта регистра).
     */
    public static Optional<HeadOrganizationType> fromLabelOrValue(String raw) {
        if (raw == null || raw.isBlank()) {
            return Optional.empty();
        }
        String normalized = raw.trim().toLowerCase(Locale.ROOT);
        return Arrays.stream(values())
                .filter(type -> type.value.equals(normalized)
                        || type.label.toLowerCase(Locale.ROOT).equals(normalized))
                .findFirst();
    }

    /**
     * @throws IllegalArgumentException если значение неизвестно
     */
    public static HeadOrganizationType requireFromLabelOrValue(String raw) {
        return fromLabelOrValue(raw)
                .orElseThrow(() -> new IllegalArgumentException("Invalid head organization type."));
    }

    /**
     * @return опции вида ГО для UI
     */
    public static List<LabelValue> options() {
        return Arrays.stream(values())
                .map(type -> new LabelValue(type.value, type.label))
                .toList();
    }
}
