package ru.sail.salesoutlets.domain.enums;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import org.junit.jupiter.api.Test;

class HeadOrganizationTypeTest {

    @Test
    void fromLabelOrValueAcceptsCodeAndLabel() {
        assertEquals(
                HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                HeadOrganizationType.fromLabelOrValue("ooo").orElseThrow()
        );
        assertEquals(
                HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                HeadOrganizationType.fromLabelOrValue("ООО").orElseThrow()
        );
        assertTrue(HeadOrganizationType.fromLabelOrValue("xyz").isEmpty());
    }
}
