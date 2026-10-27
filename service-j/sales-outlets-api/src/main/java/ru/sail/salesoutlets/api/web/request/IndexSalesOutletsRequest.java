package ru.sail.salesoutlets.api.web.request;

import jakarta.servlet.http.HttpServletRequest;
import jakarta.validation.constraints.Pattern;
import jakarta.validation.constraints.Size;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.regex.Matcher;
import ru.sail.salesoutlets.api.web.validation.InAllowedSalesOutletColumn;
import ru.sail.salesoutlets.api.web.validation.ValidSalesOutletStatus;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;

/**
 * Query-параметры GET {@code /api/sales-outlets} после разбора из HTTP-запроса.
 */
public class IndexSalesOutletsRequest {

    private static final java.util.regex.Pattern COLUMN_FILTER_PATTERN =
            java.util.regex.Pattern.compile("^column_filters\\[(.+)]$");

    @Size(max = 255)
    private String search;

    @ValidSalesOutletStatus
    private String status;

    private Map<String, @Size(max = 255) String> columnFilters;

    @InAllowedSalesOutletColumn
    private String sort;

    @Pattern(regexp = "asc|desc", flags = Pattern.Flag.CASE_INSENSITIVE, message = "Выбранное значение недопустимо.")
    private String direction;

    private List<@InAllowedSalesOutletColumn String> columns;

    private Integer page;

    private Integer perPage;

    public String getSearch() {
        return search;
    }

    public String getStatus() {
        return status;
    }

    public Map<String, String> getColumnFilters() {
        return columnFilters;
    }

    public String getSort() {
        return sort;
    }

    public String getDirection() {
        return direction;
    }

    public List<String> getColumns() {
        return columns;
    }

    public Integer getPage() {
        return page;
    }

    public Integer getPerPage() {
        return perPage;
    }

    /**
     * Разбирает query-параметры (включая {@code column_filters[key]} и {@code columns[]}).
     */
    public static IndexSalesOutletsRequest fromHttpRequest(HttpServletRequest httpRequest) {
        IndexSalesOutletsRequest request = new IndexSalesOutletsRequest();
        request.search = blankToNull(httpRequest.getParameter("search"));
        request.status = blankToNull(httpRequest.getParameter("status"));
        request.columnFilters = extractColumnFilters(httpRequest);
        request.sort = blankToNull(httpRequest.getParameter("sort"));
        request.direction = blankToNull(httpRequest.getParameter("direction"));
        request.columns = extractColumns(httpRequest);
        request.page = parseInteger(httpRequest.getParameter("page"));
        request.perPage = parseInteger(httpRequest.getParameter("per_page"));
        return request;
    }

    /** Собирает нормализованный query DTO с учётом allowed columns. */
    public SalesOutletIndexQueryDto toQueryDto(SalesOutletsMetadataRepository metadataRepository) {
        return SalesOutletIndexQueryDto.normalize(
                search,
                status,
                columnFilters,
                sort,
                direction,
                page,
                perPage,
                columns,
                metadataRepository.allowedColumnKeys()
        );
    }

    private static Map<String, String> extractColumnFilters(HttpServletRequest httpRequest) {
        Map<String, String> filters = new LinkedHashMap<>();
        for (Map.Entry<String, String[]> entry : httpRequest.getParameterMap().entrySet()) {
            Matcher matcher = COLUMN_FILTER_PATTERN.matcher(entry.getKey());
            if (!matcher.matches()) {
                continue;
            }
            String[] values = entry.getValue();
            if (values == null || values.length == 0 || values[0] == null) {
                continue;
            }
            filters.put(matcher.group(1), values[0]);
        }
        return filters.isEmpty() ? null : Map.copyOf(filters);
    }

    private static List<String> extractColumns(HttpServletRequest httpRequest) {
        String[] bracket = httpRequest.getParameterValues("columns[]");
        String[] plain = httpRequest.getParameterValues("columns");
        if (bracket == null && plain == null) {
            return null;
        }
        List<String> columns = new ArrayList<>();
        if (bracket != null) {
            for (String value : bracket) {
                if (value != null) {
                    columns.add(value);
                }
            }
        }
        if (plain != null) {
            for (String value : plain) {
                if (value != null) {
                    columns.add(value);
                }
            }
        }
        return columns;
    }

    private static String blankToNull(String value) {
        if (value == null || value.isBlank()) {
            return null;
        }
        return value;
    }

    private static Integer parseInteger(String value) {
        if (value == null || value.isBlank()) {
            return null;
        }
        try {
            return Integer.parseInt(value.trim());
        } catch (NumberFormatException ex) {
            // Невалидное число оставим null; Bean Validation на page/per_page не строгая
            // (как в service-a: nullable integer). Непарсимое → нормализация возьмёт default.
            return null;
        }
    }
}
