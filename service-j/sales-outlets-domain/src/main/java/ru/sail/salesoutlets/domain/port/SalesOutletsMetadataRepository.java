package ru.sail.salesoutlets.domain.port;

import java.util.List;
import ru.sail.salesoutlets.domain.metadata.SalesOutletColumn;

/**
 * Порт метаданных колонок таблицы торговых точек.
 */
public interface SalesOutletsMetadataRepository {

    /** Описание колонок таблицы. */
    List<SalesOutletColumn> columns();

    /** Ключи колонок, доступных для sort/columns query-параметров. */
    List<String> allowedColumnKeys();
}
