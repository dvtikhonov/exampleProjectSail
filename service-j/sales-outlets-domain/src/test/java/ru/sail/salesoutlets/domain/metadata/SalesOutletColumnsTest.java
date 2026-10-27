package ru.sail.salesoutlets.domain.metadata;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import org.junit.jupiter.api.Test;

class SalesOutletColumnsTest {

    @Test
    void searchableDbColumnsExcludeStatusLabel() {
        assertTrue(SalesOutletColumns.searchableDbColumns().contains("shop"));
        assertFalse(SalesOutletColumns.searchableDbColumns().contains("status_label"));
        assertFalse(SalesOutletColumns.searchableDbColumns().contains("status"));
    }

    @Test
    void sortColumnMapMapsStatusLabelToStatus() {
        assertEquals("status", SalesOutletColumns.sortColumnMap().get("status_label"));
        assertEquals(SalesOutletColumns.all().size(), SalesOutletColumns.sortColumnMap().size());
    }

    @Test
    void columnFilterTypeMapIncludesStatusLabel() {
        assertEquals(
                SalesOutletColumns.FILTER_STATUS_LABEL,
                SalesOutletColumns.columnFilterTypeMap().get("status_label")
        );
        assertEquals(
                SalesOutletColumns.FILTER_LIKE_PREFIX,
                SalesOutletColumns.columnFilterTypeMap().get("shop")
        );
    }

    @Test
    void keysMatchAllColumns() {
        assertEquals(
                SalesOutletColumns.all().stream().map(SalesOutletColumn::key).toList(),
                SalesOutletColumns.keys()
        );
    }
}
