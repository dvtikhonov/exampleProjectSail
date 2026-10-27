package ru.sail.salesoutlets.domain.dto;

import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;

/**
 * DTO обновления головной организации (POST head-organization).
 */
public record UpdateHeadOrganizationDto(
        String headOrganization,
        HeadOrganizationType headOrganizationType
) {

    /**
     * Собирает DTO из уже проверенных строковых полей (после Bean Validation).
     *
     * @throws IllegalArgumentException если вид ГО неизвестен
     */
    public static UpdateHeadOrganizationDto fromValidated(String headOrganization, String headOrganizationType) {
        return new UpdateHeadOrganizationDto(
                headOrganization == null ? "" : headOrganization.trim(),
                HeadOrganizationType.requireFromLabelOrValue(headOrganizationType)
        );
    }
}
