package ru.sail.salesoutlets.api.web;

import jakarta.validation.Valid;
import java.util.Map;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PatchMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;
import ru.sail.salesoutlets.api.web.request.IndexSalesOutletsRequest;
import ru.sail.salesoutlets.api.web.request.UpdateHeadOrganizationRequest;
import ru.sail.salesoutlets.api.web.request.UpdateSalesOutletRequest;
import ru.sail.salesoutlets.api.web.response.SalesOutletIndexResponse;
import ru.sail.salesoutlets.application.SalesOutletRowMapper;
import ru.sail.salesoutlets.domain.SalesOutlet;
import ru.sail.salesoutlets.domain.port.SalesOutletService;
import ru.sail.salesoutlets.domain.port.SalesOutletTableMetaProvider;
import ru.sail.salesoutlets.domain.port.SalesOutletsMetadataRepository;

/**
 * REST API торговых точек. Формат ответов совместим с service-a.
 */
@RestController
@RequestMapping("/api/sales-outlets")
public class SalesOutletsController {

    private final SalesOutletService salesOutletService;
    private final SalesOutletsMetadataRepository metadataRepository;
    private final SalesOutletTableMetaProvider tableMetaProvider;

    public SalesOutletsController(
            SalesOutletService salesOutletService,
            SalesOutletsMetadataRepository metadataRepository,
            SalesOutletTableMetaProvider tableMetaProvider
    ) {
        this.salesOutletService = salesOutletService;
        this.metadataRepository = metadataRepository;
        this.tableMetaProvider = tableMetaProvider;
    }

    /** Список торговых точек с фильтрами, сортировкой и пагинацией. */
    @GetMapping
    public ResponseEntity<Map<String, Object>> index(IndexSalesOutletsRequest request) {
        var result = salesOutletService.index(request.toQueryDto(metadataRepository));
        return ResponseEntity.ok(SalesOutletIndexResponse.from(result, tableMetaProvider));
    }

    /** Полное обновление торговой точки. */
    @PatchMapping("/{id}")
    public ResponseEntity<Map<String, Object>> update(
            @PathVariable long id,
            @Valid @RequestBody UpdateSalesOutletRequest request
    ) {
        SalesOutlet updated = salesOutletService.update(id, request.toDto());
        return ResponseEntity.ok(SalesOutletRowMapper.fromDomain(updated).toMap());
    }

    /** Обновление головной организации. */
    @PostMapping("/{id}/head-organization")
    public ResponseEntity<Map<String, Object>> updateHeadOrganization(
            @PathVariable long id,
            @Valid @RequestBody UpdateHeadOrganizationRequest request
    ) {
        SalesOutlet updated = salesOutletService.updateHeadOrganization(id, request.toDto());
        return ResponseEntity.ok(SalesOutletRowMapper.fromDomain(updated).toMap());
    }

    /** Soft delete торговой точки. */
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> destroy(@PathVariable long id) {
        salesOutletService.delete(id);
        return ResponseEntity.noContent().build();
    }
}
