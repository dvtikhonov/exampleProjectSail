package ru.sail.salesoutlets.application;

import java.util.List;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumn;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumns;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;

/**
 * Метаданные колонок из {@link SalesOutletColumns} (без БД).
 */
public class StaticSalesOutletsMetadataRepository implements SalesOutletsMetadataRepository {

    @Override
    public List<SalesOutletColumn> columns() {
        return SalesOutletColumns.all();
    }

    @Override
    public List<String> allowedColumnKeys() {
        return SalesOutletColumns.keys();
    }
}
