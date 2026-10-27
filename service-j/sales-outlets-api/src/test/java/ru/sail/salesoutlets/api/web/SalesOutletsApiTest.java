package ru.sail.salesoutlets.api.web;

import static org.hamcrest.Matchers.hasKey;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.delete;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.patch;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

import java.util.Map;
import jakarta.persistence.EntityManager;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.autoconfigure.web.servlet.AutoConfigureMockMvc;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.http.MediaType;
import org.springframework.jdbc.core.JdbcTemplate;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.test.web.servlet.request.MockHttpServletRequestBuilder;
import org.springframework.transaction.annotation.Transactional;
import ru.sail.salesoutlets.api.auth.GatewayAuth;
import ru.sail.salesoutlets.api.auth.TrustGatewayAuthFilter;
import ru.sail.salesoutlets.api.support.SalesOutletTestSeeder;

/**
 * Feature-тесты REST API {@code /api/sales-outlets} (паритет с service-a / service-e).
 * БД: {@code sail_db_testing}; изменения откатываются через {@code @Transactional}.
 */
@SpringBootTest
@AutoConfigureMockMvc
@Transactional
@ActiveProfiles("test")
class SalesOutletsApiTest {

    private static final long GATEWAY_USER_ID = 1L;

    @Autowired
    private MockMvc mockMvc;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Autowired
    private EntityManager entityManager;

    @BeforeEach
    void setUp() {
        SalesOutletTestSeeder.seed(jdbcTemplate);
    }

    /** Сбрасывает Hibernate-сессию, чтобы JDBC видел изменения из MockMvc в той же TX. */
    private void flushPersistence() {
        entityManager.flush();
        entityManager.clear();
    }

    @Test
    void itReturnsUnauthorizedWithoutGatewayHeader() throws Exception {
        mockMvc.perform(get("/api/sales-outlets").accept(MediaType.APPLICATION_JSON))
                .andExpect(status().isUnauthorized())
                .andExpect(jsonPath("$.message").value(TrustGatewayAuthFilter.UNAUTHORIZED_MESSAGE));
    }

    @Test
    void itReturnsSalesOutletsList() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.meta.pagination.total").value(8))
                .andExpect(jsonPath("$.data[0].id").value(1001))
                .andExpect(jsonPath("$.data[0].status_label").value("Есть изменения"))
                .andExpect(jsonPath("$.data[0].row_tone").value("danger"))
                .andExpect(jsonPath("$.data[0].head_organization_type").value("ip"))
                .andExpect(jsonPath("$.data[0].head_organization_type_label").value("ИП"));
    }

    @Test
    void itFiltersByStatus() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets").param("status", "approved")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.meta.pagination.total").value(3))
                .andExpect(jsonPath("$.data[0].status").value("approved"));
    }

    @Test
    void itSearchesSalesOutlets() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets").param("search", "Фермер")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.meta.pagination.total").value(1))
                .andExpect(jsonPath("$.data[0].id").value(1008));
    }

    @Test
    void itAppliesColumnFilters() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets")
                        .param("column_filters[shop]", "Белгород")
                        .param("column_filters[inn]", "3118")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.meta.pagination.total").value(1))
                .andExpect(jsonPath("$.data[0].id").value(1002));
    }

    @Test
    void itSortsSalesOutlets() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets")
                        .param("sort", "shop")
                        .param("direction", "desc")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.data[0].shop").value("Тамбов"))
                .andExpect(jsonPath("$.data[1].shop").value("Старый Оскол"));
    }

    @Test
    void itLimitsPerPage() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets")
                        .param("per_page", "2")
                        .param("page", "2")))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.data.length()").value(3))
                .andExpect(jsonPath("$.meta.filters.per_page").value(5))
                .andExpect(jsonPath("$.meta.pagination.current_page").value(2))
                .andExpect(jsonPath("$.meta.pagination.from").value(6));
    }

    @Test
    void itValidatesSalesOutletsIndexQuery() throws Exception {
        mockMvc.perform(withGateway(get("/api/sales-outlets")
                        .param("status", "archived")
                        .param("sort", "unknown")
                        .param("direction", "sideways")
                        .param("columns[]", "bad")))
                .andExpect(status().isUnprocessableEntity())
                .andExpect(jsonPath("$.errors", hasKey("status")))
                .andExpect(jsonPath("$.errors", hasKey("sort")))
                .andExpect(jsonPath("$.errors", hasKey("direction")))
                .andExpect(jsonPath("$.errors", hasKey("columns.0")));
    }

    @Test
    void itUpdatesHeadOrganization() throws Exception {
        mockMvc.perform(withGateway(post("/api/sales-outlets/1001/head-organization"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "head_organization": "Новая головная организация",
                                  "head_organization_type": "АО"
                                }
                                """))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.id").value(1001))
                .andExpect(jsonPath("$.head_organization").value("Новая головная организация"))
                .andExpect(jsonPath("$.head_organization_type").value("ao"))
                .andExpect(jsonPath("$.head_organization_type_label").value("АО"))
                .andExpect(jsonPath("$.user_id").value((int) GATEWAY_USER_ID));

        flushPersistence();
        assertSalesOutletHas(Map.ofEntries(
                Map.entry("id", 1001L),
                Map.entry("head_organization", "Новая головная организация"),
                Map.entry("head_organization_type", "ao"),
                Map.entry("user_id", GATEWAY_USER_ID)
        ));
    }

    @Test
    void itUpdatesSalesOutlet() throws Exception {
        mockMvc.perform(withGateway(patch("/api/sales-outlets/1001"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "shop": "Воронеж",
                                  "manager": "Новый менеджер",
                                  "curator": "Новый куратор",
                                  "name": "Обновленная точка",
                                  "inn": "3666123456",
                                  "head_organization": "Новая головная организация",
                                  "head_organization_type": "ООО",
                                  "organization_name": "ООО Новая организация",
                                  "status": "approved",
                                  "id": 999999,
                                  "row_tone": "danger",
                                  "approved": "Нет"
                                }
                                """))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.id").value(1001))
                .andExpect(jsonPath("$.shop").value("Воронеж"))
                .andExpect(jsonPath("$.manager").value("Новый менеджер"))
                .andExpect(jsonPath("$.curator").value("Новый куратор"))
                .andExpect(jsonPath("$.name").value("Обновленная точка"))
                .andExpect(jsonPath("$.inn").value("3666123456"))
                .andExpect(jsonPath("$.head_organization").value("Новая головная организация"))
                .andExpect(jsonPath("$.head_organization_type").value("ooo"))
                .andExpect(jsonPath("$.head_organization_type_label").value("ООО"))
                .andExpect(jsonPath("$.organization_name").value("ООО Новая организация"))
                .andExpect(jsonPath("$.status").value("approved"))
                .andExpect(jsonPath("$.status_label").value("Одобрено"))
                .andExpect(jsonPath("$.user_id").value((int) GATEWAY_USER_ID))
                .andExpect(jsonPath("$.row_tone").value("success"));

        flushPersistence();
        assertSalesOutletHas(Map.ofEntries(
                Map.entry("id", 1001L),
                Map.entry("shop", "Воронеж"),
                Map.entry("manager", "Новый менеджер"),
                Map.entry("curator", "Новый куратор"),
                Map.entry("name", "Обновленная точка"),
                Map.entry("inn", "3666123456"),
                Map.entry("head_organization", "Новая головная организация"),
                Map.entry("head_organization_type", "ooo"),
                Map.entry("organization_name", "ООО Новая организация"),
                Map.entry("status", "approved"),
                Map.entry("user_id", GATEWAY_USER_ID)
        ));

        Integer phantom = jdbcTemplate.queryForObject(
                "SELECT COUNT(*) FROM sales_outlets WHERE id = ?",
                Integer.class,
                999999
        );
        org.junit.jupiter.api.Assertions.assertEquals(0, phantom);
    }

    @Test
    void itValidatesSalesOutletUpdate() throws Exception {
        mockMvc.perform(withGateway(patch("/api/sales-outlets/1001"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "shop": "",
                                  "manager": "",
                                  "curator": "",
                                  "name": "",
                                  "inn": "bad-inn",
                                  "head_organization": "",
                                  "head_organization_type": "ЗАО",
                                  "organization_name": "",
                                  "status": "archived"
                                }
                                """))
                .andExpect(status().isUnprocessableEntity())
                .andExpect(jsonPath("$.errors", hasKey("shop")))
                .andExpect(jsonPath("$.errors", hasKey("manager")))
                .andExpect(jsonPath("$.errors", hasKey("curator")))
                .andExpect(jsonPath("$.errors", hasKey("name")))
                .andExpect(jsonPath("$.errors", hasKey("inn")))
                .andExpect(jsonPath("$.errors", hasKey("head_organization")))
                .andExpect(jsonPath("$.errors", hasKey("head_organization_type")))
                .andExpect(jsonPath("$.errors", hasKey("organization_name")))
                .andExpect(jsonPath("$.errors", hasKey("status")));
    }

    @Test
    void itReturnsNotFoundForUnknownSalesOutletOnUpdate() throws Exception {
        mockMvc.perform(withGateway(patch("/api/sales-outlets/999999"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(updateSalesOutletJson()))
                .andExpect(status().isNotFound());
    }

    @Test
    void itSoftDeletesSalesOutletAndStoresLastUser() throws Exception {
        mockMvc.perform(withGateway(delete("/api/sales-outlets/1001")))
                .andExpect(status().isNoContent());

        flushPersistence();
        Map<String, Object> row = jdbcTemplate.queryForMap(
                "SELECT deleted_at, user_id FROM sales_outlets WHERE id = ?",
                1001
        );
        org.junit.jupiter.api.Assertions.assertNotNull(row.get("deleted_at"));
        org.junit.jupiter.api.Assertions.assertEquals(GATEWAY_USER_ID, ((Number) row.get("user_id")).longValue());
    }

    @Test
    void itAutomaticallyStoresGatewayUserHeaderOnSalesOutletUpdate() throws Exception {
        mockMvc.perform(withGateway(post("/api/sales-outlets/1001/head-organization"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "head_organization": "Головная через gateway",
                                  "head_organization_type": "ООО"
                                }
                                """))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.user_id").value((int) GATEWAY_USER_ID));

        flushPersistence();
        assertSalesOutletHas(Map.ofEntries(
                Map.entry("id", 1001L),
                Map.entry("head_organization", "Головная через gateway"),
                Map.entry("user_id", GATEWAY_USER_ID)
        ));
    }

    @Test
    void itValidatesHeadOrganizationUpdate() throws Exception {
        mockMvc.perform(withGateway(post("/api/sales-outlets/1001/head-organization"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "head_organization": "",
                                  "head_organization_type": "ЗАО"
                                }
                                """))
                .andExpect(status().isUnprocessableEntity())
                .andExpect(jsonPath("$.errors", hasKey("head_organization")))
                .andExpect(jsonPath("$.errors", hasKey("head_organization_type")));
    }

    @Test
    void itReturnsNotFoundForUnknownSalesOutletOnHeadOrganizationUpdate() throws Exception {
        mockMvc.perform(withGateway(post("/api/sales-outlets/999999/head-organization"))
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("""
                                {
                                  "head_organization": "Новая головная организация",
                                  "head_organization_type": "ООО"
                                }
                                """))
                .andExpect(status().isNotFound());
    }

    private static MockHttpServletRequestBuilder withGateway(MockHttpServletRequestBuilder builder) {
        return builder
                .accept(MediaType.APPLICATION_JSON)
                .header(GatewayAuth.USER_ID_HEADER, String.valueOf(GATEWAY_USER_ID));
    }

    private void assertSalesOutletHas(Map<String, Object> expected) {
        long id = ((Number) expected.get("id")).longValue();
        Map<String, Object> row = jdbcTemplate.queryForMap("SELECT * FROM sales_outlets WHERE id = ?", id);
        for (Map.Entry<String, Object> entry : expected.entrySet()) {
            Object actual = row.get(entry.getKey());
            Object want = entry.getValue();
            if (want instanceof Number wantNumber && actual instanceof Number actualNumber) {
                org.junit.jupiter.api.Assertions.assertEquals(
                        wantNumber.longValue(),
                        actualNumber.longValue(),
                        "Mismatch for column [" + entry.getKey() + "]"
                );
            } else {
                org.junit.jupiter.api.Assertions.assertEquals(
                        String.valueOf(want),
                        String.valueOf(actual),
                        "Mismatch for column [" + entry.getKey() + "]"
                );
            }
        }
    }

    private static String updateSalesOutletJson() {
        return """
                {
                  "shop": "Воронеж",
                  "manager": "Новый менеджер",
                  "curator": "Новый куратор",
                  "name": "Обновленная точка",
                  "inn": "3666123456",
                  "head_organization": "Новая головная организация",
                  "head_organization_type": "ООО",
                  "organization_name": "ООО Новая организация",
                  "status": "approved"
                }
                """;
    }
}
