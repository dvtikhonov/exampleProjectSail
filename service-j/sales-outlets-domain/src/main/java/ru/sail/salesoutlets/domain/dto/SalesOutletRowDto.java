package ru.sail.salesoutlets.domain.dto;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Строка таблицы торговых точек для JSON API (паритет с shared SalesOutletRowDto).
 */
public record SalesOutletRowDto(
        long id,
        String shop,
        String manager,
        String curator,
        String name,
        String inn,
        String headOrganization,
        String headOrganizationType,
        String headOrganizationTypeLabel,
        String organizationName,
        String status,
        String statusLabel,
        String approved,
        Long userId,
        String rowTone
) {

    /**
     * Сериализует строку в snake_case для JSON.
     *
     * @param includeRowTone включать ли {@code row_tone}
     */
    public Map<String, Object> toMap(boolean includeRowTone) {
        Map<String, Object> row = new LinkedHashMap<>();
        row.put("id", id);
        row.put("shop", shop);
        row.put("manager", manager);
        row.put("curator", curator);
        row.put("name", name);
        row.put("inn", inn);
        row.put("head_organization", headOrganization);
        row.put("head_organization_type", headOrganizationType);
        row.put("head_organization_type_label", headOrganizationTypeLabel);
        row.put("organization_name", organizationName);
        row.put("status", status);
        row.put("status_label", statusLabel);
        row.put("approved", approved);
        row.put("user_id", userId);
        if (includeRowTone) {
            row.put("row_tone", rowTone);
        }
        return row;
    }

    public Map<String, Object> toMap() {
        return toMap(true);
    }
}
