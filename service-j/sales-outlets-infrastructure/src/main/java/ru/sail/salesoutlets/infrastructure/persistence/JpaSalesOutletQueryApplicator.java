package ru.sail.salesoutlets.infrastructure.persistence;

import jakarta.persistence.criteria.CriteriaBuilder;
import jakarta.persistence.criteria.Order;
import jakarta.persistence.criteria.Predicate;
import jakarta.persistence.criteria.Root;
import java.util.ArrayList;
import java.util.List;
import java.util.Locale;
import java.util.Map;
import org.springframework.data.jpa.domain.Specification;
import ru.sail.salesoutlets.domain.dto.SalesOutletFilterDto;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumn;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumns;

/**
 * Применяет фильтры, поиск и сортировку к JPA Specification.
 * Логика совместима с shared/sales-outlets-domain и service-e Doctrine applicator.
 */
public class JpaSalesOutletQueryApplicator {

    private static final Map<String, String> DB_COLUMN_TO_PROPERTY = Map.ofEntries(
            Map.entry("id", "id"),
            Map.entry("shop", "shop"),
            Map.entry("manager", "manager"),
            Map.entry("curator", "curator"),
            Map.entry("name", "name"),
            Map.entry("inn", "inn"),
            Map.entry("head_organization", "headOrganization"),
            Map.entry("head_organization_type", "headOrganizationType"),
            Map.entry("organization_name", "organizationName"),
            Map.entry("status", "status"),
            Map.entry("approved", "approved"),
            Map.entry("user_id", "userId")
    );

    /**
     * Собирает Specification: soft-delete, status, column_filters, search, sort.
     */
    public Specification<SalesOutletJpaEntity> toSpecification(SalesOutletFilterDto filters) {
        return (root, query, cb) -> {
            List<Predicate> predicates = new ArrayList<>();
            predicates.add(cb.isNull(root.get("deletedAt")));
            applyStatus(predicates, root, cb, filters.status());
            applyColumnFilters(predicates, root, cb, filters.columnFilters());
            applySearch(predicates, root, cb, filters.search());

            if (query != null && query.getResultType() != Long.class && query.getResultType() != long.class) {
                query.orderBy(buildOrder(root, cb, filters.sort(), filters.direction()));
            }

            return cb.and(predicates.toArray(Predicate[]::new));
        };
    }

    private void applyStatus(
            List<Predicate> predicates,
            Root<SalesOutletJpaEntity> root,
            CriteriaBuilder cb,
            String status
    ) {
        if (SalesOutletStatus.tryFrom(status).isEmpty()) {
            return;
        }
        predicates.add(cb.equal(root.get("status"), status.trim().toLowerCase(Locale.ROOT)));
    }

    private void applyColumnFilters(
            List<Predicate> predicates,
            Root<SalesOutletJpaEntity> root,
            CriteriaBuilder cb,
            Map<String, String> columnFilters
    ) {
        for (Map.Entry<String, String> entry : SalesOutletColumns.likePrefixFilterColumnMap().entrySet()) {
            String prefix = columnFilters.getOrDefault(entry.getKey(), "");
            if (prefix.isEmpty() || "%".equals(prefix) || "%%".equals(prefix)) {
                continue;
            }
            String property = dbColumnToProperty(entry.getValue());
            predicates.add(cb.like(root.get(property).as(String.class), prefix + "%"));
        }

        for (SalesOutletColumn column : SalesOutletColumns.all()) {
            if (!SalesOutletColumns.FILTER_STATUS_LABEL.equals(column.filterType())) {
                continue;
            }
            List<String> statuses = SalesOutletStatus.valuesByLabelSubstring(
                    columnFilters.getOrDefault(column.key(), "")
            );
            if (!statuses.isEmpty()) {
                predicates.add(root.get(dbColumnToProperty(column.dbColumn())).in(statuses));
            }
        }
    }

    private void applySearch(
            List<Predicate> predicates,
            Root<SalesOutletJpaEntity> root,
            CriteriaBuilder cb,
            String search
    ) {
        if (search == null || search.isBlank()) {
            return;
        }
        String pattern = "%" + search + "%";
        List<Predicate> orPredicates = new ArrayList<>();
        for (String dbColumn : SalesOutletColumns.searchableDbColumns()) {
            String property = dbColumnToProperty(dbColumn);
            orPredicates.add(cb.like(root.get(property).as(String.class), pattern));
        }
        if (!orPredicates.isEmpty()) {
            predicates.add(cb.or(orPredicates.toArray(Predicate[]::new)));
        }
    }

    private Order buildOrder(
            Root<SalesOutletJpaEntity> root,
            CriteriaBuilder cb,
            String sort,
            String direction
    ) {
        String dbColumn = SalesOutletColumns.sortColumnMap().getOrDefault(sort, "id");
        String property = dbColumnToProperty(dbColumn);
        return "desc".equalsIgnoreCase(direction)
                ? cb.desc(root.get(property))
                : cb.asc(root.get(property));
    }

    private static String dbColumnToProperty(String dbColumn) {
        return DB_COLUMN_TO_PROPERTY.getOrDefault(dbColumn, dbColumn);
    }
}
