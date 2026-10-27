package ru.sail.salesoutlets.infrastructure.persistence;

import org.springframework.data.jpa.repository.JpaRepository;

/**
 * Spring Data доступ к таблице {@code users}.
 */
public interface UserSpringDataRepository extends JpaRepository<UserJpaEntity, Long> {
}
