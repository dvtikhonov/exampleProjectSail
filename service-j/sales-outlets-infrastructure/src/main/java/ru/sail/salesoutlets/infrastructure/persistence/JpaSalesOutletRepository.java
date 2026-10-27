package ru.sail.salesoutlets.infrastructure.persistence;

import java.util.List;
import java.util.Optional;
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
 * JPA-реализация порта {@link SalesOutletRepository}: чтение и сохранение снимка агрегата.
 */
@Transactional
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
    public Optional<SalesOutlet> findById(long id) {
        return springDataRepository.findByIdAndDeletedAtIsNull(id).map(SalesOutletEntityMapper::toDomain);
    }

    @Override
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
    public SalesOutlet save(SalesOutlet salesOutlet) {
        SalesOutletJpaEntity entity = springDataRepository.findByIdAndDeletedAtIsNull(salesOutlet.id())
                .orElseThrow(() -> new SalesOutletNotFoundException(salesOutlet.id()));
        SalesOutletEntityMapper.copyOnto(salesOutlet, entity);
        SalesOutletJpaEntity saved = springDataRepository.save(entity);
        return SalesOutletEntityMapper.toDomain(saved);
    }
}
