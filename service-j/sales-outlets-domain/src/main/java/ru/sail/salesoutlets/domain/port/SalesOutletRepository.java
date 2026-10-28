package ru.sail.salesoutlets.domain.port;

import java.util.Optional;
import java.util.function.UnaryOperator;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginatedResultDto;

/**
 * Порт репозитория торговых точек (доменная модель, без JPA).
 * Изменение агрегата выполняет домен; репозиторий читает снимок, применяет переданную функцию и сохраняет результат.
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

    /**
     * В одной операции загружает активную торговую точку, применяет {@code change} и сохраняет результат.
     * Правила изменения остаются на агрегате {@link SalesOutlet}: функция только вызывает их.
     *
     * @param id     идентификатор торговой точки
     * @param change функция изменения: получает активный агрегат и возвращает новый снимок
     * @return сохранённый снимок
     * @throws ru.sail.salesoutlets.domain.SalesOutletNotFoundException если активной точки с таким id нет
     */
    SalesOutlet modify(long id, UnaryOperator<SalesOutlet> change);
}
