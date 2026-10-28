package ru.sail.salesoutlets.infrastructure.persistence;

import jakarta.persistence.LockModeType;
import java.util.Optional;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.JpaSpecificationExecutor;
import org.springframework.data.jpa.repository.Lock;
import org.springframework.data.jpa.repository.Query;
import org.springframework.data.repository.query.Param;

/**
 * Spring Data доступ к таблице {@code sales_outlets}.
 */
public interface SalesOutletSpringDataRepository
        extends JpaRepository<SalesOutletJpaEntity, Long>, JpaSpecificationExecutor<SalesOutletJpaEntity> {

    /** Находит активную (не soft-deleted) торговую точку. */
    Optional<SalesOutletJpaEntity> findByIdAndDeletedAtIsNull(Long id);

    /**
     * Активная строка ({@code deleted_at IS NULL}) с пессимистической блокировкой
     * ({@code SELECT … FOR UPDATE}).
     */
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT e FROM SalesOutletJpaEntity e WHERE e.id = :id AND e.deletedAt IS NULL")
    Optional<SalesOutletJpaEntity> findActiveByIdForUpdate(@Param("id") Long id);
}
