package ru.sail.salesoutlets.domain.dto;

import java.util.Map;

/**
 * Параметры фильтрации/сортировки списка торговых точек.
 *
 * @param search         глобальный поиск
 * @param status         фильтр по статусу (value enum или пусто)
 * @param columnFilters  фильтры по колонкам (API key → значение)
 * @param sort           ключ сортировки
 * @param direction      {@code asc} или {@code desc}
 */
public record SalesOutletFilterDto(
        String search,
        String status,
        Map<String, String> columnFilters,
        String sort,
        String direction
) {
    public SalesOutletFilterDto {
        search = search == null ? "" : search;
        status = status == null ? "" : status;
        columnFilters = columnFilters == null ? Map.of() : Map.copyOf(columnFilters);
        sort = sort == null || sort.isBlank() ? "id" : sort;
        direction = "desc".equalsIgnoreCase(direction) ? "desc" : "asc";
    }

    public static SalesOutletFilterDto from(SalesOutletIndexQueryDto query) {
        return new SalesOutletFilterDto(
                query.search(),
                query.status(),
                query.columnFilters(),
                query.sort(),
                query.direction()
        );
    }
}
