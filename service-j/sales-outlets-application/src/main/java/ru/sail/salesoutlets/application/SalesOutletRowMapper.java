package ru.sail.salesoutlets.application;

import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.SalesOutletRowDto;

/**
 * Преобразует доменную модель в DTO строки таблицы для API-ответа.
 */
public final class SalesOutletRowMapper {

    private SalesOutletRowMapper() {
    }

    /** Преобразует доменную модель в DTO строки таблицы. */
    public static SalesOutletRowDto fromDomain(SalesOutlet salesOutlet) {
        return new SalesOutletRowDto(
                salesOutlet.id(),
                salesOutlet.shop(),
                salesOutlet.manager(),
                salesOutlet.curator(),
                salesOutlet.name(),
                salesOutlet.inn(),
                salesOutlet.headOrganization(),
                salesOutlet.headOrganizationType().value(),
                salesOutlet.headOrganizationType().label(),
                salesOutlet.organizationName(),
                salesOutlet.status().value(),
                salesOutlet.status().label(),
                salesOutlet.approved(),
                salesOutlet.userId(),
                salesOutlet.status().rowTone()
        );
    }
}
