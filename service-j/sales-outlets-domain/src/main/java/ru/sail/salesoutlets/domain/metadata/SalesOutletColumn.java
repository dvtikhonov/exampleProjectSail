package ru.sail.salesoutlets.domain.metadata;

import java.util.LinkedHashMap;
import java.util.Map;
import java.util.Optional;

/**
 * Описание колонки таблицы торговых точек.
 *
 * @param key         API-ключ колонки
 * @param label       человекочитаемая метка
 * @param sortable    участвует ли в сортировке
 * @param searchable  участвует ли в глобальном поиске
 * @param filterType  тип column filter ({@link SalesOutletColumns#FILTER_LIKE_PREFIX} и т.п.), nullable
 * @param sortColumn  DB-колонка для ORDER BY, если отличается от key (например status_label → status)
 */
public record SalesOutletColumn(
        String key,
        String label,
        boolean sortable,
        boolean searchable,
        String filterType,
        String sortColumn
) {

    public String dbColumn() {
        return sortColumn != null && !sortColumn.isBlank() ? sortColumn : key;
    }

    public Optional<String> filterTypeOptional() {
        return filterType == null || filterType.isBlank() ? Optional.empty() : Optional.of(filterType);
    }

    /**
     * Базовая сериализация метаданных колонки (без UI width/align).
     */
    public Map<String, Object> toMap() {
        Map<String, Object> map = new LinkedHashMap<>();
        map.put("key", key);
        map.put("label", label);
        map.put("sortable", sortable);
        map.put("searchable", searchable);
        filterTypeOptional().ifPresent(type -> map.put("filterType", type));
        if (sortColumn != null && !sortColumn.isBlank()) {
            map.put("sortColumn", sortColumn);
        }
        return map;
    }
}
