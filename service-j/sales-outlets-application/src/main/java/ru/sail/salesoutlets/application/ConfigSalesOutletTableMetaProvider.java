package ru.sail.salesoutlets.application;

import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import ru.sail.salesoutlets.domain.dto.LabelValue;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumn;
import ru.sail.salesoutlets.domain.port.SalesOutletTableMetaProvider;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;

/**
 * Метаданные UI таблицы: колонки (merge UI-настроек) и опции статусов.
 */
public class ConfigSalesOutletTableMetaProvider implements SalesOutletTableMetaProvider {

    private final SalesOutletsMetadataRepository metadataRepository;
    private final Map<String, Map<String, Object>> columnsUi;
    private final String statusOptionsAllLabel;

    public ConfigSalesOutletTableMetaProvider(
            SalesOutletsMetadataRepository metadataRepository,
            Map<String, Map<String, Object>> columnsUi,
            String statusOptionsAllLabel
    ) {
        this.metadataRepository = metadataRepository;
        this.columnsUi = columnsUi == null ? Map.of() : deepCopy(columnsUi);
        this.statusOptionsAllLabel = statusOptionsAllLabel == null || statusOptionsAllLabel.isBlank()
                ? SalesOutletColumnsUi.DEFAULT_STATUS_OPTIONS_ALL_LABEL
                : statusOptionsAllLabel;
    }

    /** Использует UI-настройки и подпись «Все статусы» по умолчанию. */
    public ConfigSalesOutletTableMetaProvider(SalesOutletsMetadataRepository metadataRepository) {
        this(
                metadataRepository,
                SalesOutletColumnsUi.defaultColumnsUi(),
                SalesOutletColumnsUi.DEFAULT_STATUS_OPTIONS_ALL_LABEL
        );
    }

    @Override
    public List<Map<String, Object>> columns() {
        List<Map<String, Object>> result = new ArrayList<>();
        for (SalesOutletColumn column : metadataRepository.columns()) {
            Map<String, Object> merged = new LinkedHashMap<>(column.toMap());
            Map<String, Object> ui = columnsUi.get(column.key());
            if (ui != null) {
                merged.putAll(ui);
            }
            result.add(Map.copyOf(merged));
        }
        return List.copyOf(result);
    }

    @Override
    public List<LabelValue> statusOptions() {
        List<LabelValue> options = new ArrayList<>();
        options.add(new LabelValue("", statusOptionsAllLabel));
        options.addAll(SalesOutletStatus.options());
        return List.copyOf(options);
    }

    private static Map<String, Map<String, Object>> deepCopy(Map<String, Map<String, Object>> source) {
        Map<String, Map<String, Object>> copy = new LinkedHashMap<>();
        for (Map.Entry<String, Map<String, Object>> entry : source.entrySet()) {
            copy.put(entry.getKey(), entry.getValue() == null ? Map.of() : Map.copyOf(entry.getValue()));
        }
        return Map.copyOf(copy);
    }
}
