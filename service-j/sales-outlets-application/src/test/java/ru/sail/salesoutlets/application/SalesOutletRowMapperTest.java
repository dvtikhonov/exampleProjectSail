package ru.sail.salesoutlets.application;

import static org.junit.jupiter.api.Assertions.assertEquals;

import java.time.Instant;
import org.junit.jupiter.api.Test;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.SalesOutletRowDto;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

class SalesOutletRowMapperTest {

    @Test
    void fromDomainMapsLabelsAndTone() {
        SalesOutlet outlet = new SalesOutlet(
                1L,
                "Shop",
                "Manager",
                "Curator",
                "Name",
                "1234567890",
                "Head Org",
                HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                "Org Name",
                SalesOutletStatus.APPROVED,
                "yes",
                42L,
                Instant.parse("2026-01-01T00:00:00Z"),
                null
        );

        SalesOutletRowDto row = SalesOutletRowMapper.fromDomain(outlet);

        assertEquals("ooo", row.headOrganizationType());
        assertEquals("ООО", row.headOrganizationTypeLabel());
        assertEquals("approved", row.status());
        assertEquals("Одобрено", row.statusLabel());
        assertEquals("success", row.rowTone());
        assertEquals(42L, row.userId());
    }
}
