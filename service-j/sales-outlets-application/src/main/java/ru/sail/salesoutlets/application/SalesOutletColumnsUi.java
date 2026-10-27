package ru.sail.salesoutlets.application;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * UI-настройки колонок таблицы (width / align / cellType), паритет с config sales_outlets.
 */
public final class SalesOutletColumnsUi {

    public static final String DEFAULT_STATUS_OPTIONS_ALL_LABEL = "Все статусы";

    private SalesOutletColumnsUi() {
    }

    /**
     * Значения по умолчанию для merge поверх {@link ru.sail.salesoutlets.domain.metadata.SalesOutletColumns}.
     */
    public static Map<String, Map<String, Object>> defaultColumnsUi() {
        Map<String, Map<String, Object>> ui = new LinkedHashMap<>();
        ui.put("id", Map.of("width", 120, "align", "center"));
        ui.put("shop", Map.of("width", 150));
        ui.put("manager", Map.of("width", 160));
        ui.put("curator", Map.of("width", 160));
        ui.put("name", Map.of("width", 200));
        ui.put("inn", Map.of("width", 140, "align", "right"));
        ui.put("head_organization", Map.of("width", 220, "cellType", "headOrganizationPoptip"));
        ui.put("head_organization_type", Map.of("width", 100, "align", "center"));
        ui.put("organization_name", Map.of("width", 220));
        ui.put("status_label", Map.of("width", 160, "align", "center", "cellType", "statusBadge"));
        ui.put("approved", Map.of("width", 120, "align", "center"));
        ui.put("user_id", Map.of("width", 140, "align", "center"));
        return Map.copyOf(ui);
    }
}
