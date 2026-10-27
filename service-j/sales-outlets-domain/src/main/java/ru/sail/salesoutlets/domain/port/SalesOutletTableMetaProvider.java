package ru.sail.salesoutlets.domain.port;

import java.util.List;
import java.util.Map;
import ru.sail.salesoutlets.domain.dto.LabelValue;

/**
 * Порт метаданных UI таблицы (колонки с width/align и опции статусов).
 */
public interface SalesOutletTableMetaProvider {

    /**
     * Колонки таблицы с UI-настройками (width, align, cellType и т.д.).
     */
    List<Map<String, Object>> columns();

    /**
     * Опции фильтра по статусу, включая пункт «Все».
     */
    List<LabelValue> statusOptions();
}
