package ru.sail.salesoutlets.domain.dto;

import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Метаданные пагинации списка торговых точек.
 */
public record SalesOutletPaginationDto(
        int currentPage,
        int lastPage,
        int perPage,
        int total,
        int from,
        int to
) {

    /**
     * Вычисляет метаданные пагинации по total, perPage и currentPage.
     */
    public static SalesOutletPaginationDto fromCounts(int total, int perPage, int currentPage) {
        int safePerPage = Math.max(perPage, 1);
        int lastPage = Math.max((int) Math.ceil((double) total / safePerPage), 1);
        int from = total == 0 ? 0 : ((currentPage - 1) * perPage) + 1;
        int to = total == 0 ? 0 : Math.min(currentPage * perPage, total);

        return new SalesOutletPaginationDto(currentPage, lastPage, perPage, total, from, to);
    }

    /**
     * Сериализует пагинацию в snake_case для JSON API.
     */
    public Map<String, Integer> toMap() {
        Map<String, Integer> map = new LinkedHashMap<>();
        map.put("current_page", currentPage);
        map.put("last_page", lastPage);
        map.put("per_page", perPage);
        map.put("total", total);
        map.put("from", from);
        map.put("to", to);
        return map;
    }
}
