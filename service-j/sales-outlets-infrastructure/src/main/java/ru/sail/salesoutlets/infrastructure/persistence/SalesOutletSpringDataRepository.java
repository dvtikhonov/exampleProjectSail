package ru.sail.salesoutlets.infrastructure.persistence;

import java.util.Optional;
import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.JpaSpecificationExecutor;

/**
 * Spring Data доступ к таблице {@code sales_outlets}.
 */
public interface SalesOutletSpringDataRepository
        extends JpaRepository<SalesOutletJpaEntity, Long>, JpaSpecificationExecutor<SalesOutletJpaEntity> {

    /** Находит активную (не soft-deleted) торговую точку. */
    Optional<SalesOutletJpaEntity> findByIdAndDeletedAtIsNull(Long id);
}
