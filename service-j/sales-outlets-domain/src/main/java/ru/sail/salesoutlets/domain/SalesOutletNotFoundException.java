package ru.sail.salesoutlets.domain;

/**
 * Торговая точка не найдена или soft-deleted.
 */
public class SalesOutletNotFoundException extends RuntimeException {

    private final long salesOutletId;

    public SalesOutletNotFoundException(long salesOutletId) {
        super("Sales outlet with id " + salesOutletId + " not found.");
        this.salesOutletId = salesOutletId;
    }

    public long salesOutletId() {
        return salesOutletId;
    }
}
