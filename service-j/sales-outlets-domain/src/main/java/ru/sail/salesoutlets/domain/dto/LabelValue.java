package ru.sail.salesoutlets.domain.dto;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Пара value/label для опций фильтров и справочников в JSON API.
 */
public record LabelValue(String value, String label) {

    public Map<String, String> toMap() {
        Map<String, String> map = new LinkedHashMap<>();
        map.put("value", value);
        map.put("label", label);
        return map;
    }
}
