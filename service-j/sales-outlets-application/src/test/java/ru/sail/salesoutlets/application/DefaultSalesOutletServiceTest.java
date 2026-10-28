package ru.sail.salesoutlets.application;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertSame;
import static org.junit.jupiter.api.Assertions.assertThrows;

import java.time.Clock;
import java.time.Instant;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.function.UnaryOperator;
import org.junit.jupiter.api.Test;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.SalesOutletNotFoundException;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexQueryDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletIndexResultDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginatedResultDto;
import ru.sail.salesoutlets.domain.dto.SalesOutletPaginationDto;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;
import ru.sail.salesoutlets.domain.port.SalesOutletRepository;

class DefaultSalesOutletServiceTest {

    private static final Instant NOW = Instant.parse("2026-10-27T05:00:00Z");
    private static final Clock CLOCK = Clock.fixed(NOW, ZoneOffset.UTC);

    @Test
    void indexWrapsRepositoryPage() {
        SalesOutlet outlet = sampleOutlet();
        SalesOutletIndexQueryDto query = SalesOutletIndexQueryDto.normalize(
                "", "", null, "id", "asc", 1, 10, null, List.of("id", "shop")
        );
        FakeRepository repository = new FakeRepository(outlet);
        DefaultSalesOutletService service = service(repository, 7L);

        SalesOutletIndexResultDto result = service.index(query);

        assertEquals(1, result.salesOutlets().size());
        assertSame(outlet, result.salesOutlets().getFirst());
        assertEquals(query, result.query());
        assertEquals(1, result.pagination().total());
    }

    @Test
    void updateLoadsAppliesAndSaves() {
        FakeRepository repository = new FakeRepository(sampleOutlet());
        DefaultSalesOutletService service = service(repository, 7L);
        UpdateSalesOutletDto dto = new UpdateSalesOutletDto(
                "Воронеж", "Новый менеджер", "Новый куратор", "Обновленная точка",
                "3666123456", "Новая головная организация",
                HeadOrganizationType.LIMITED_LIABILITY_COMPANY, "ООО Новая организация",
                SalesOutletStatus.APPROVED
        );

        SalesOutlet saved = service.update(1L, dto);

        assertEquals("Воронеж", saved.shop());
        assertEquals(HeadOrganizationType.LIMITED_LIABILITY_COMPANY, saved.headOrganizationType());
        assertEquals(SalesOutletStatus.APPROVED, saved.status());
        assertEquals("no", saved.approved());
        assertEquals(7L, saved.userId());
        assertEquals(NOW, saved.updatedAt());
        assertNull(saved.deletedAt());
        assertSame(saved, repository.saved);
    }

    @Test
    void updateHeadOrganizationKeepsOtherFields() {
        FakeRepository repository = new FakeRepository(sampleOutlet());
        DefaultSalesOutletService service = service(repository, 7L);

        SalesOutlet saved = service.updateHeadOrganization(
                1L,
                new UpdateHeadOrganizationDto("Головная", HeadOrganizationType.JOINT_STOCK_COMPANY)
        );

        assertEquals("shop", saved.shop());
        assertEquals("1234567890", saved.inn());
        assertEquals("Головная", saved.headOrganization());
        assertEquals(HeadOrganizationType.JOINT_STOCK_COMPANY, saved.headOrganizationType());
        assertEquals(7L, saved.userId());
        assertEquals(NOW, saved.updatedAt());
        assertNull(saved.deletedAt());
    }

    @Test
    void deleteMarksDeletedAndSaves() {
        FakeRepository repository = new FakeRepository(sampleOutlet());
        DefaultSalesOutletService service = service(repository, 7L);

        service.delete(1L);

        assertEquals("shop", repository.saved.shop());
        assertEquals(7L, repository.saved.userId());
        assertEquals(NOW, repository.saved.updatedAt());
        assertEquals(NOW, repository.saved.deletedAt());
    }

    @Test
    void updateKeepsPreviousAuthorWhenGatewayUserIsMissing() {
        FakeRepository repository = new FakeRepository(sampleOutlet());
        DefaultSalesOutletService service = service(repository, null);

        SalesOutlet saved = service.updateHeadOrganization(
                1L,
                new UpdateHeadOrganizationDto("Головная", HeadOrganizationType.JOINT_STOCK_COMPANY)
        );

        assertEquals(9L, saved.userId());
        assertEquals(NOW, saved.updatedAt());
    }

    @Test
    void missingOutletIsNotSaved() {
        FakeRepository repository = new FakeRepository(null);
        DefaultSalesOutletService service = service(repository, 7L);
        UpdateSalesOutletDto dto = new UpdateSalesOutletDto(
                "s", "m", "c", "n", "1234567890", "ho",
                HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR, "org",
                SalesOutletStatus.REVIEW
        );

        assertThrows(SalesOutletNotFoundException.class, () -> service.update(1L, dto));
        assertThrows(SalesOutletNotFoundException.class, () -> service.updateHeadOrganization(
                1L,
                new UpdateHeadOrganizationDto("ho", HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR)
        ));
        assertThrows(SalesOutletNotFoundException.class, () -> service.delete(1L));
        assertNull(repository.saved);
    }

    private static DefaultSalesOutletService service(FakeRepository repository, Long userId) {
        return new DefaultSalesOutletService(repository, () -> userId, CLOCK);
    }

    private static SalesOutlet sampleOutlet() {
        return new SalesOutlet(
                1L, "shop", "manager", "curator", "name", "1234567890", "head",
                HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR, "org",
                SalesOutletStatus.REVIEW, "no", 9L,
                Instant.parse("2026-01-01T00:00:00Z"),
                null
        );
    }

    private static final class FakeRepository implements SalesOutletRepository {
        private final SalesOutlet outlet;
        private SalesOutlet saved;

        private FakeRepository(SalesOutlet outlet) {
            this.outlet = outlet;
        }

        @Override
        public Optional<SalesOutlet> findById(long id) {
            if (outlet == null || outlet.id() != id) {
                return Optional.empty();
            }
            return Optional.of(outlet);
        }

        @Override
        public SalesOutletPaginatedResultDto paginate(SalesOutletIndexQueryDto queryDto) {
            return new SalesOutletPaginatedResultDto(
                    List.of(outlet),
                    SalesOutletPaginationDto.fromCounts(1, queryDto.perPage(), queryDto.page())
            );
        }

        @Override
        public SalesOutlet save(SalesOutlet salesOutlet) {
            this.saved = salesOutlet;
            return salesOutlet;
        }

        @Override
        public SalesOutlet modify(long id, UnaryOperator<SalesOutlet> change) {
            SalesOutlet current = findById(id).orElseThrow(() -> new SalesOutletNotFoundException(id));
            this.saved = change.apply(current);
            return this.saved;
        }
    }
}
