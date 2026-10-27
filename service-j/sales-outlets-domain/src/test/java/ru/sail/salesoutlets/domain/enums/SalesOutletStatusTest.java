package ru.sail.salesoutlets.domain.enums;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.List;
import org.junit.jupiter.api.Test;

class SalesOutletStatusTest {

    @Test
    void tryFromParsesKnownValues() {
        assertEquals(SalesOutletStatus.APPROVED, SalesOutletStatus.tryFrom("approved").orElseThrow());
        assertEquals(SalesOutletStatus.REVIEW, SalesOutletStatus.tryFrom("REVIEW").orElseThrow());
        assertTrue(SalesOutletStatus.tryFrom("unknown").isEmpty());
    }

    @Test
    void optionsMatchCases() {
        assertEquals(3, SalesOutletStatus.options().size());
        assertEquals("approved", SalesOutletStatus.options().getFirst().value());
        assertEquals("Одобрено", SalesOutletStatus.options().getFirst().label());
    }

    @Test
    void valuesByLabelSubstringMatchesLabelFragmentIgnoringCase() {
        assertEquals(List.of(), SalesOutletStatus.valuesByLabelSubstring(null));
        assertEquals(List.of(), SalesOutletStatus.valuesByLabelSubstring(""));
        assertEquals(List.of(), SalesOutletStatus.valuesByLabelSubstring("   "));
        assertEquals(List.of("approved"), SalesOutletStatus.valuesByLabelSubstring("одоб"));
        assertEquals(List.of("review"), SalesOutletStatus.valuesByLabelSubstring("ПРОВЕРК"));
        assertEquals(List.of("blocked"), SalesOutletStatus.valuesByLabelSubstring("Изменен"));
        assertEquals(List.of(), SalesOutletStatus.valuesByLabelSubstring("нет такого"));
    }
}
