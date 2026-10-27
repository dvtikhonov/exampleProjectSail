package ru.sail.salesoutlets.infrastructure.persistence;

import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

/**
 * Маппинг JPA Entity ↔ доменная модель {@link SalesOutlet}.
 */
public final class SalesOutletEntityMapper {

    private SalesOutletEntityMapper() {
    }

    /** Маппит JPA-сущность в доменную модель. */
    public static SalesOutlet toDomain(SalesOutletJpaEntity entity) {
        return new SalesOutlet(
                entity.getId(),
                entity.getShop(),
                entity.getManager(),
                entity.getCurator(),
                entity.getName(),
                entity.getInn(),
                entity.getHeadOrganization(),
                HeadOrganizationType.requireFromLabelOrValue(entity.getHeadOrganizationType()),
                entity.getOrganizationName(),
                SalesOutletStatus.fromValue(entity.getStatus()),
                entity.getApproved(),
                entity.getUserId(),
                entity.getUpdatedAt(),
                entity.getDeletedAt()
        );
    }

    /** Переносит снимок агрегата в уже загруженную JPA-сущность. {@code id} и {@code createdAt} не трогает. */
    public static void copyOnto(SalesOutlet salesOutlet, SalesOutletJpaEntity entity) {
        entity.setShop(salesOutlet.shop());
        entity.setManager(salesOutlet.manager());
        entity.setCurator(salesOutlet.curator());
        entity.setName(salesOutlet.name());
        entity.setInn(salesOutlet.inn());
        entity.setHeadOrganization(salesOutlet.headOrganization());
        entity.setHeadOrganizationType(salesOutlet.headOrganizationType().value());
        entity.setOrganizationName(salesOutlet.organizationName());
        entity.setStatus(salesOutlet.status().value());
        entity.setApproved(salesOutlet.approved());
        entity.setUserId(salesOutlet.userId());
        entity.setUpdatedAt(salesOutlet.updatedAt());
        entity.setDeletedAt(salesOutlet.deletedAt());
    }
}
