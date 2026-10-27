package ru.sail.salesoutlets.domain;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;

import java.time.Instant;
import org.junit.jupiter.api.Test;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

class SalesOutletTest {

    private static final Instant UPDATED_AT = Instant.parse("2026-01-01T00:00:00Z");
    private static final Instant CHANGED_AT = Instant.parse("2026-10-27T05:00:00Z");

    @Test
    void applyReplacesEditableFieldsAndKeepsIdentity() {
        SalesOutlet current = activeOutlet();
        UpdateSalesOutletDto dto = new UpdateSalesOutletDto(
                "Воронеж",
                "Новый менеджер",
                "Новый куратор",
                "Обновленная точка",
                "3666123456",
                "Новая головная организация",
                HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                "ООО Новая организация",
                SalesOutletStatus.APPROVED
        );

        SalesOutlet applied = current.apply(dto);

        assertEquals(1L, applied.id());
        assertEquals("Воронеж", applied.shop());
        assertEquals("Новый менеджер", applied.manager());
        assertEquals("Новый куратор", applied.curator());
        assertEquals("Обновленная точка", applied.name());
        assertEquals("3666123456", applied.inn());
        assertEquals("Новая головная организация", applied.headOrganization());
        assertEquals(HeadOrganizationType.LIMITED_LIABILITY_COMPANY, applied.headOrganizationType());
        assertEquals("ООО Новая организация", applied.organizationName());
        assertEquals(SalesOutletStatus.APPROVED, applied.status());
        assertEquals("no", applied.approved());
        assertEquals(9L, applied.userId());
        assertEquals(UPDATED_AT, applied.updatedAt());
        assertNull(applied.deletedAt());
    }

    @Test
    void applyHeadOrganizationChangesOnlyHeadFields() {
        SalesOutlet applied = activeOutlet().apply(
                new UpdateHeadOrganizationDto("Головная", HeadOrganizationType.JOINT_STOCK_COMPANY)
        );

        assertEquals("shop", applied.shop());
        assertEquals("1234567890", applied.inn());
        assertEquals(SalesOutletStatus.REVIEW, applied.status());
        assertEquals("Головная", applied.headOrganization());
        assertEquals(HeadOrganizationType.JOINT_STOCK_COMPANY, applied.headOrganizationType());
        assertEquals(9L, applied.userId());
        assertEquals(UPDATED_AT, applied.updatedAt());
    }

    @Test
    void recordChangeStoresAuthorAndInstant() {
        SalesOutlet changed = activeOutlet().recordChange(7L, CHANGED_AT);

        assertEquals("shop", changed.shop());
        assertEquals(7L, changed.userId());
        assertEquals(CHANGED_AT, changed.updatedAt());
        assertNull(changed.deletedAt());
    }

    @Test
    void recordChangeKeepsPreviousAuthorWhenUserIsMissing() {
        SalesOutlet changed = activeOutlet().recordChange(null, CHANGED_AT);

        assertEquals(9L, changed.userId());
        assertEquals(CHANGED_AT, changed.updatedAt());
    }

    @Test
    void markDeletedStoresAuthorAndSameInstant() {
        SalesOutlet deleted = activeOutlet().markDeleted(7L, CHANGED_AT);

        assertEquals("shop", deleted.shop());
        assertEquals(SalesOutletStatus.REVIEW, deleted.status());
        assertEquals(7L, deleted.userId());
        assertEquals(CHANGED_AT, deleted.updatedAt());
        assertEquals(CHANGED_AT, deleted.deletedAt());
    }

    @Test
    void markDeletedKeepsPreviousAuthorWhenUserIsMissing() {
        SalesOutlet deleted = activeOutlet().markDeleted(null, CHANGED_AT);

        assertEquals(9L, deleted.userId());
        assertEquals(CHANGED_AT, deleted.deletedAt());
    }

    @Test
    void deletedOutletRejectsFurtherChanges() {
        SalesOutlet deleted = activeOutlet().markDeleted(7L, CHANGED_AT);
        UpdateSalesOutletDto dto = new UpdateSalesOutletDto(
                "s", "m", "c", "n", "1234567890", "ho",
                HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR, "org",
                SalesOutletStatus.REVIEW
        );

        assertThrows(IllegalStateException.class, () -> deleted.apply(dto));
        assertThrows(IllegalStateException.class, () -> deleted.apply(
                new UpdateHeadOrganizationDto("ho", HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR)
        ));
        assertThrows(IllegalStateException.class, () -> deleted.recordChange(1L, CHANGED_AT));
        assertThrows(IllegalStateException.class, () -> deleted.markDeleted(1L, CHANGED_AT));
    }

    private static SalesOutlet activeOutlet() {
        return new SalesOutlet(
                1L,
                "shop",
                "manager",
                "curator",
                "name",
                "1234567890",
                "head",
                HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR,
                "org",
                SalesOutletStatus.REVIEW,
                "no",
                9L,
                UPDATED_AT,
                null
        );
    }
}
