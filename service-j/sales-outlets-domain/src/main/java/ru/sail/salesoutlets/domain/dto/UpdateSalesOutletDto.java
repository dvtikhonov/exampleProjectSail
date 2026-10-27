package ru.sail.salesoutlets.domain.dto;

import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

/**
 * DTO полного обновления торговой точки (PATCH).
 */
public record UpdateSalesOutletDto(
        String shop,
        String manager,
        String curator,
        String name,
        String inn,
        String headOrganization,
        HeadOrganizationType headOrganizationType,
        String organizationName,
        SalesOutletStatus status
) {

    /**
     * Собирает DTO из уже проверенных строковых полей (после Bean Validation).
     *
     * @throws IllegalArgumentException если вид ГО или статус неизвестны
     */
    public static UpdateSalesOutletDto fromValidated(
            String shop,
            String manager,
            String curator,
            String name,
            String inn,
            String headOrganization,
            String headOrganizationType,
            String organizationName,
            String status
    ) {
        return new UpdateSalesOutletDto(
                trim(shop),
                trim(manager),
                trim(curator),
                trim(name),
                trim(inn),
                trim(headOrganization),
                HeadOrganizationType.requireFromLabelOrValue(headOrganizationType),
                trim(organizationName),
                SalesOutletStatus.fromValue(status)
        );
    }

    private static String trim(String value) {
        return value == null ? "" : value.trim();
    }
}
