package ru.sail.salesoutlets.infrastructure.persistence;

import java.util.List;
import java.util.Optional;
import java.util.function.UnaryOperator;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.jpa.domain.Specification;
import org.springframework.transaction.annotation.Transactional;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.SalesOutletNotFoundException;
import ru.sail.salesoutlets.domain.dto.SalesOutletFilterDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginatedResultDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginationDto;
import ru.sail.salesoutlets.domain.port.SalesOutletRepository;

/**
 * JPA-реализация порта {@link SalesOutletRepository}.
 * Чтения идут в read-only транзакции; {@link #save} и {@link #modify} — в записывающей.
 */
public class JpaSalesOutletRepository implements SalesOutletRepository {

    private final SalesOutletSpringDataRepository springDataRepository;
    private final JpaSalesOutletQueryApplicator queryApplicator;

    public JpaSalesOutletRepository(
            SalesOutletSpringDataRepository springDataRepository,
            JpaSalesOutletQueryApplicator queryApplicator
    ) {
        this.springDataRepository = springDataRepository;
        this.queryApplicator = queryApplicator;
    }

    @Override
    @Transactional(readOnly = true)
    public Optional<SalesOutlet> findById(long id) {
        return springDataRepository.findByIdAndDeletedAtIsNull(id).map(SalesOutletEntityMapper::toDomain);
    }

    @Override
    @Transactional(readOnly = true)
    public SalesOutletPaginatedResultDto paginate(SalesOutletIndexQueryDto queryDto) {
        Specification<SalesOutletJpaEntity> specification =
                queryApplicator.toSpecification(SalesOutletFilterDto.from(queryDto));

        PageRequest pageRequest = PageRequest.of(queryDto.page() - 1, queryDto.perPage());
        Page<SalesOutletJpaEntity> page = springDataRepository.findAll(specification, pageRequest);

        List<SalesOutlet> items = page.getContent().stream()
                .map(SalesOutletEntityMapper::toDomain)
                .toList();

        return new SalesOutletPaginatedResultDto(
                items,
                SalesOutletPaginationDto.fromCounts(
                        (int) page.getTotalElements(),
                        queryDto.perPage(),
                        queryDto.page()
                )
        );
    }

    @Override
    @Transactional
    public SalesOutlet save(SalesOutlet salesOutlet) {
        SalesOutletJpaEntity entity = springDataRepository.findByIdAndDeletedAtIsNull(salesOutlet.id())
                .orElseThrow(() -> new SalesOutletNotFoundException(salesOutlet.id()));
        SalesOutletEntityMapper.copyOnto(salesOutlet, entity);
        SalesOutletJpaEntity saved = springDataRepository.save(entity);
        return SalesOutletEntityMapper.toDomain(saved);
    }

    @Override
    @Transactional
    public SalesOutlet modify(long id, UnaryOperator<SalesOutlet> change) {
        SalesOutletJpaEntity entity = springDataRepository.findActiveByIdForUpdate(id)
                .orElseThrow(() -> new SalesOutletNotFoundException(id));
        SalesOutlet changed = change.apply(SalesOutletEntityMapper.toDomain(entity));
        SalesOutletEntityMapper.copyOnto(changed, entity);
        return SalesOutletEntityMapper.toDomain(springDataRepository.save(entity));
    }
}
