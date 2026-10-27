package ru.sail.salesoutlets.api.web.response;

import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import ru.sail.salesoutlets.application.SalesOutletRowMapper;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.LabelValue;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexResultDto;
import ru.sail.salesoutlets.domain.port.SalesOutletTableMetaProvider;

/**
 * JSON-ответ списка торговых точек ({@code data} + {@code meta}), паритет с service-a.
 */
public final class SalesOutletIndexResponse {

    private SalesOutletIndexResponse() {
    }

    /**
     * Сериализует результат index в Map для JSON.
     */
    public static Map<String, Object> from(
            SalesOutletIndexResultDto result,
            SalesOutletTableMetaProvider tableMetaProvider
    ) {
        SalesOutletIndexQueryDto query = result.query();

        List<Map<String, Object>> data = result.salesOutlets().stream()
                .map(SalesOutletIndexResponse::rowToMap)
                .toList();

        Map<String, Object> filters = new LinkedHashMap<>();
        filters.put("search", query.search());
        filters.put("status", query.status());
        filters.put("column_filters", query.columnFilters());
        filters.put("sort", query.sort());
        filters.put("direction", query.direction());
        filters.put("page", result.pagination().currentPage());
        filters.put("per_page", query.perPage());
        filters.put("columns", query.columns());

        List<Map<String, String>> statusOptions = tableMetaProvider.statusOptions().stream()
                .map(LabelValue::toMap)
                .toList();

        Map<String, Object> meta = new LinkedHashMap<>();
        meta.put("columns", tableMetaProvider.columns());
        meta.put("filters", filters);
        meta.put("pagination", result.pagination().toMap());
        meta.put("status_options", statusOptions);

        Map<String, Object> body = new LinkedHashMap<>();
        body.put("data", data);
        body.put("meta", meta);
        return body;
    }

    private static Map<String, Object> rowToMap(SalesOutlet salesOutlet) {
        return SalesOutletRowMapper.fromDomain(salesOutlet).toMap();
    }
}
