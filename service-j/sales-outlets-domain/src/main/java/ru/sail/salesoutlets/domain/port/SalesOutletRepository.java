package ru.sail.salesoutlets.domain.port;

import java.util.Optional;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginatedResultDto;

/**
 * Порт репозитория торговых точек (доменная модель, без JPA).
 * Изменение агрегата выполняет домен; репозиторий только читает и сохраняет снимок.
 */
public interface SalesOutletRepository {

    /** Находит активную (не удалённую) торговую точку по id. */
    Optional<SalesOutlet> findById(long id);

    /** Возвращает страницу торговых точек с учётом фильтров и сортировки. */
    SalesOutletPaginatedResultDto paginate(SalesOutletIndexQueryDto queryDto);

    /**
     * Сохраняет уже изменённый агрегат и возвращает снимок из хранилища.
     *
     * @throws ru.sail.salesoutlets.domain.SalesOutletNotFoundException если активной точки с таким id нет
     */
    SalesOutlet save(SalesOutlet salesOutlet);
}
