package ru.sail.salesoutlets.domain.dto;

import java.util.List;
import ru.sail.salesoutlets.domain.SalesOutlet;

/**
 * Результат use-case index: строки, пагинация и применённые фильтры.
 *
 * @param salesOutlets элементы страницы
 * @param pagination   метаданные пагинации
 * @param query        применённый запрос
 */
public record SalesOutletIndexResultDto(
        List<SalesOutlet> salesOutlets,
        SalesOutletPaginationDto pagination,
        SalesOutletIndexQueryDto query
) {
    public SalesOutletIndexResultDto {
        salesOutlets = salesOutlets == null ? List.of() : List.copyOf(salesOutlets);
    }

    /** Собирает результат index из страницы репозитория и применённых фильтров. */
    public static SalesOutletIndexResultDto fromPaginatedResult(
            SalesOutletPaginatedResultDto paginatedResult,
            SalesOutletIndexQueryDto query
    ) {
        return new SalesOutletIndexResultDto(
                paginatedResult.items(),
                paginatedResult.pagination(),
                query
        );
    }
}
