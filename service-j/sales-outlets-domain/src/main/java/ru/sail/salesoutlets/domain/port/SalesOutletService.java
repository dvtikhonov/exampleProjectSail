package ru.sail.salesoutlets.domain.port;

import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexResultDto;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;

/**
 * Порт сервисного слоя торговых точек (use-cases).
 * Сервис загружает агрегат, применяет изменение и сохраняет снимок.
 */
public interface SalesOutletService {

    /** Пагинированный список торговых точек по параметрам запроса. */
    SalesOutletIndexResultDto index(SalesOutletIndexQueryDto queryDto);

    /**
     * Обновляет головную организацию торговой точки.
     *
     * @throws ru.sail.salesoutlets.domain.SalesOutletNotFoundException если активной точки нет
     */
    SalesOutlet updateHeadOrganization(long id, UpdateHeadOrganizationDto dto);

    /**
     * Обновляет поля торговой точки.
     *
     * @throws ru.sail.salesoutlets.domain.SalesOutletNotFoundException если активной точки нет
     */
    SalesOutlet update(long id, UpdateSalesOutletDto dto);

    /**
     * Помечает торговую точку удалённой (soft delete).
     *
     * @throws ru.sail.salesoutlets.domain.SalesOutletNotFoundException если активной точки нет
     */
    void delete(long id);
}
