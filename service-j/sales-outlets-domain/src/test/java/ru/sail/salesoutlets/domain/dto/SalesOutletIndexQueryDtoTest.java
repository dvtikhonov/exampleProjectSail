package ru.sail.salesoutlets.domain.dto;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.Test;

class SalesOutletIndexQueryDtoTest {

    private static final List<String> ALLOWED = List.of("id", "shop", "inn", "status");

    @Test
    void clampsPerPageToMinimumFive() {
        SalesOutletIndexQueryDto dto = SalesOutletIndexQueryDto.normalize(
                null,
                null,
                null,
                null,
                null,
                2,
                2,
                null,
                ALLOWED
        );

        assertEquals(5, dto.perPage());
        assertEquals(2, dto.page());
        assertEquals("id", dto.sort());
        assertEquals("asc", dto.direction());
    }

    @Test
    void clampsPerPageToMaximumFifty() {
        SalesOutletIndexQueryDto dto = SalesOutletIndexQueryDto.normalize(
                null,
                null,
                null,
                "shop",
                "DESC",
                1,
                100,
                null,
                ALLOWED
        );

        assertEquals(50, dto.perPage());
        assertEquals("shop", dto.sort());
        assertEquals("desc", dto.direction());
    }

    @Test
    void keepsOnlyAllowedColumnFilters() {
        SalesOutletIndexQueryDto dto = SalesOutletIndexQueryDto.normalize(
                "  Фермер  ",
                "approved",
                Map.of("shop", " Белгород ", "unknown", "x", "inn", ""),
                null,
                null,
                null,
                null,
                List.of("shop", "bad"),
                ALLOWED
        );

        assertEquals("Фермер", dto.search());
        assertEquals("approved", dto.status());
        assertEquals(Map.of("shop", "Белгород"), dto.columnFilters());
        assertEquals(List.of("shop"), dto.columns());
    }

    @Test
    void fallsBackToAllAllowedColumnsWhenRequestedEmptyAfterFilter() {
        SalesOutletIndexQueryDto dto = SalesOutletIndexQueryDto.normalize(
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                List.of("missing"),
                ALLOWED
        );

        assertEquals(ALLOWED, dto.columns());
        assertTrue(dto.columnFilters().isEmpty());
    }
}
