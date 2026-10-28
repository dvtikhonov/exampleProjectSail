package ru.sail.salesoutlets.application;

import java.time.Clock;
import java.time.Instant;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.auth.GatewayUserContext;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexResultDto;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;
import ru.sail.salesoutlets.domain.port.SalesOutletRepository;
import ru.sail.salesoutlets.domain.port.SalesOutletService;

/**
 * Сервисный слой торговых точек.
 * Изменение агрегата выполняется правилами домена внутри одной операции {@link SalesOutletRepository#modify}.
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
        Long userId = gatewayUserContext.currentUserId();
        Instant changedAt = clock.instant();
        return salesOutletRepository.modify(id, outlet -> outlet.apply(dto).recordChange(userId, changedAt));
    }

    @Override
    public SalesOutlet update(long id, UpdateSalesOutletDto dto) {
        Long userId = gatewayUserContext.currentUserId();
        Instant changedAt = clock.instant();
        return salesOutletRepository.modify(id, outlet -> outlet.apply(dto).recordChange(userId, changedAt));
    }

    @Override
    public void delete(long id) {
        Long userId = gatewayUserContext.currentUserId();
        Instant changedAt = clock.instant();
        salesOutletRepository.modify(id, outlet -> outlet.markDeleted(userId, changedAt));
    }
}
