package ru.sail.salesoutlets.application;

import java.time.Clock;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.SalesOutletNotFoundException;
import ru.sail.salesoutlets.domain.auth.GatewayUserContext;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexResultDto;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;
import ru.sail.salesoutlets.domain.port.SalesOutletRepository;
import ru.sail.salesoutlets.domain.port.SalesOutletService;

/**
 * Сервисный слой торговых точек: загружает агрегат, применяет изменение и сохраняет.
 */
public class DefaultSalesOutletService implements SalesOutletService {

    private final SalesOutletRepository salesOutletRepository;
    private final GatewayUserContext gatewayUserContext;
    private final Clock clock;

    public DefaultSalesOutletService(
            SalesOutletRepository salesOutletRepository,
            GatewayUserContext gatewayUserContext,
            Clock clock
    ) {
        this.salesOutletRepository = salesOutletRepository;
        this.gatewayUserContext = gatewayUserContext;
        this.clock = clock;
    }

    @Override
    public SalesOutletIndexResultDto index(SalesOutletIndexQueryDto queryDto) {
        return SalesOutletIndexResultDto.fromPaginatedResult(
                salesOutletRepository.paginate(queryDto),
                queryDto
        );
    }

    @Override
    public SalesOutlet updateHeadOrganization(long id, UpdateHeadOrganizationDto dto) {
        SalesOutlet changed = require(id)
                .apply(dto)
                .recordChange(gatewayUserContext.currentUserId(), clock.instant());
        return salesOutletRepository.save(changed);
    }

    @Override
    public SalesOutlet update(long id, UpdateSalesOutletDto dto) {
        SalesOutlet changed = require(id)
                .apply(dto)
                .recordChange(gatewayUserContext.currentUserId(), clock.instant());
        return salesOutletRepository.save(changed);
    }

    @Override
    public void delete(long id) {
        SalesOutlet deleted = require(id).markDeleted(gatewayUserContext.currentUserId(), clock.instant());
        salesOutletRepository.save(deleted);
    }

    private SalesOutlet require(long id) {
        return salesOutletRepository.findById(id)
                .orElseThrow(() -> new SalesOutletNotFoundException(id));
    }
}
