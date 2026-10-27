package ru.sail.salesoutlets.domain.dto;

import java.util.List;
import ru.sail.salesoutlets.domain.SalesOutlet;

/**
 * Страница торговых точек с метаданными пагинации (результат репозитория).
 *
 * @param items      элементы текущей страницы
 * @param pagination метаданные пагинации
 */
public record SalesOutletPaginatedResultDto(
        List<SalesOutlet> items,
        SalesOutletPaginationDto pagination
) {
    public SalesOutletPaginatedResultDto {
        items = items == null ? List.of() : List.copyOf(items);
    }
}
