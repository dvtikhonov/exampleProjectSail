package ru.sail.salesoutlets.api.web.request;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.fasterxml.jackson.annotation.JsonProperty;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import ru.sail.salesoutlets.api.web.validation.ValidHeadOrganizationType;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;

/**
 * Тело POST {@code /api/sales-outlets/{id}/head-organization}.
 */
@JsonIgnoreProperties(ignoreUnknown = true)
public record UpdateHeadOrganizationRequest(
        @JsonProperty("head_organization")
        @NotBlank
        @Size(max = 255)
        String headOrganization,

        @JsonProperty("head_organization_type")
        @NotBlank
        @ValidHeadOrganizationType
        String headOrganizationType
) {

    /** Собирает доменный DTO после Bean Validation. */
    public UpdateHeadOrganizationDto toDto() {
        return UpdateHeadOrganizationDto.fromValidated(headOrganization, headOrganizationType);
    }
}
