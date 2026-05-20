const isBrowser = () => typeof window !== 'undefined' && typeof window.localStorage !== 'undefined';

const readSettings = (storageKey) => {
    if (!isBrowser()) {
        return {};
    }

    try {
        return JSON.parse(window.localStorage.getItem(storageKey) ?? '{}');
    } catch {
        return {};
    }
};

const writeSettings = (storageKey, settings) => {
    if (!isBrowser()) {
        return;
    }

    window.localStorage.setItem(storageKey, JSON.stringify(settings));
};

const validColumns = (columns, availableColumns) => {
    const availableKeys = availableColumns.map((column) => column.key);

    if (!Array.isArray(columns)) {
        return null;
    }

    const selectedColumns = columns.filter((column) => availableKeys.includes(column));

    return selectedColumns.length > 0 ? selectedColumns : null;
};

const validFilters = (filters, availableColumns) => {
    const availableKeys = availableColumns.map((column) => column.key);

    if (!filters || typeof filters !== 'object' || Array.isArray(filters)) {
        return null;
    }

    return Object.fromEntries(
        Object.entries(filters).filter(
            ([key, value]) => availableKeys.includes(key) && String(value).trim() !== '',
        ),
    );
};

export const usePersistentTableSettings = (storageKey, columns) => {
    const savedSettings = readSettings(storageKey);

    const saveColumns = (selectedColumns) => {
        writeSettings(storageKey, {
            ...readSettings(storageKey),
            columns: selectedColumns,
        });
    };

    const saveFilters = (filters) => {
        writeSettings(storageKey, {
            ...readSettings(storageKey),
            column_filters: filters,
        });
    };

    return {
        savedColumns: validColumns(savedSettings.columns, columns),
        savedFilters: validFilters(savedSettings.column_filters, columns),
        saveColumns,
        saveFilters,
    };
};
