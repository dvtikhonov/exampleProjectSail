package ru.sail.salesoutlets.api.web.request;

import com.fasterxml.jackson.annotation.JsonIgnoreProperties;
import com.fasterxml.jackson.annotation.JsonProperty;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;
import ru.sail.salesoutlets.api.web.validation.ValidHeadOrganizationType;
import ru.sail.salesoutlets.api.web.validation.ValidRussianInn;
import ru.sail.salesoutlets.api.web.validation.ValidSalesOutletStatus;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;

/**
 * Тело PATCH {@code /api/sales-outlets/{id}} (snake_case JSON).
 */
@JsonIgnoreProperties(ignoreUnknown = true)
public record UpdateSalesOutletRequest(
        @NotBlank
        @Size(max = 255)
        String shop,

        @NotBlank
        @Size(max = 255)
        String manager,

        @NotBlank
        @Size(max = 255)
        String curator,

        @NotBlank
        @Size(max = 255)
        String name,

        @NotBlank
        @ValidRussianInn
        String inn,

        @JsonProperty("head_organization")
        @NotBlank
        @Size(max = 255)
        String headOrganization,

        @JsonProperty("head_organization_type")
        @NotBlank
        @ValidHeadOrganizationType
        String headOrganizationType,

        @JsonProperty("organization_name")
        @NotBlank
        @Size(max = 255)
        String organizationName,

        @NotBlank
        @ValidSalesOutletStatus
        String status
) {

    /** Собирает доменный DTO после Bean Validation. */
    public UpdateSalesOutletDto toDto() {
        return UpdateSalesOutletDto.fromValidated(
                shop,
                manager,
                curator,
                name,
                inn,
                headOrganization,
                headOrganizationType,
                organizationName,
                status
        );
    }
}
