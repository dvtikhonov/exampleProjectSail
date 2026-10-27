package ru.sail.salesoutlets.api.support;

import java.util.List;
import org.springframework.jdbc.core.JdbcTemplate;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

/**
 * Заполняет таблицу {@code sales_outlets} фикстурами для Feature-тестов API.
 * Id начинаются с 1001 (паритет с service-a / service-e).
 */
public final class SalesOutletTestSeeder {

    private SalesOutletTestSeeder() {
    }

    /** Очищает таблицу в текущей транзакции и вставляет 8 торговых точек. */
    public static void seed(JdbcTemplate jdbcTemplate) {
        jdbcTemplate.update("DELETE FROM sales_outlets");

        for (FixtureRow row : rows()) {
            jdbcTemplate.update(
                    """
                    INSERT INTO sales_outlets (
                        id, shop, manager, curator, name, inn,
                        head_organization, head_organization_type, organization_name,
                        status, approved, created_at, updated_at, deleted_at, user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), NULL, NULL)
                    """,
                    row.id(),
                    row.shop(),
                    row.manager(),
                    row.curator(),
                    row.name(),
                    row.inn(),
                    row.headOrganization(),
                    row.headOrganizationType().value(),
                    row.organizationName(),
                    row.status().value(),
                    row.approved()
            );
        }
    }

    private static List<FixtureRow> rows() {
        return List.of(
                new FixtureRow(
                        1001,
                        "Белгород",
                        "Брюхненко С. Ю.",
                        "Коршунов М. С.",
                        "Колхоз",
                        "311300065310",
                        "ИП Рудев Востин Минович",
                        HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR,
                        "ИП Рудев Востин Игорь Васильевич",
                        SalesOutletStatus.BLOCKED,
                        "Нет"
                ),
                new FixtureRow(
                        1002,
                        "Белгород",
                        "Брюхненко С. Ю.",
                        "Коршунов М. С.",
                        "Колхоз",
                        "311800277237",
                        "ИП Майгатов Любовь Александровна",
                        HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR,
                        "ИП Майгатова Любовь Александровна",
                        SalesOutletStatus.BLOCKED,
                        "Нет"
                ),
                new FixtureRow(
                        1003,
                        "Воронеж",
                        "Морозова Е. А.",
                        "Поляков А. В.",
                        "Северный",
                        "3662111223",
                        "ООО Северный продукт",
                        HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                        "ООО Северный продукт",
                        SalesOutletStatus.REVIEW,
                        "Частично"
                ),
                new FixtureRow(
                        1004,
                        "Курск",
                        "Семенов И. П.",
                        "Лебедева А. Н.",
                        "Центральный",
                        "4632014589",
                        "ООО Центральная сеть",
                        HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                        "ООО Центральная сеть",
                        SalesOutletStatus.APPROVED,
                        "Да"
                ),
                new FixtureRow(
                        1005,
                        "Липецк",
                        "Иванова М. А.",
                        "Андреев К. О.",
                        "Южный",
                        "4825098765",
                        "АО Южная торговля",
                        HeadOrganizationType.JOINT_STOCK_COMPANY,
                        "АО Южная торговля",
                        SalesOutletStatus.REVIEW,
                        "Частично"
                ),
                new FixtureRow(
                        1006,
                        "Орел",
                        "Кузнецов Р. Д.",
                        "Петрова Л. С.",
                        "Партнер",
                        "5753123456",
                        "ИП Ковалев Сергей Петрович",
                        HeadOrganizationType.INDIVIDUAL_ENTREPRENEUR,
                        "ИП Ковалев Сергей Петрович",
                        SalesOutletStatus.APPROVED,
                        "Да"
                ),
                new FixtureRow(
                        1007,
                        "Тамбов",
                        "Громова Н. И.",
                        "Смирнов Д. Е.",
                        "Оптима",
                        "6829012345",
                        "ООО Оптима маркет",
                        HeadOrganizationType.LIMITED_LIABILITY_COMPANY,
                        "ООО Оптима маркет",
                        SalesOutletStatus.BLOCKED,
                        "Нет"
                ),
                new FixtureRow(
                        1008,
                        "Старый Оскол",
                        "Фролов П. С.",
                        "Никитина Ю. В.",
                        "Фермер",
                        "3128012345",
                        "СПК Фермер",
                        HeadOrganizationType.AGRICULTURAL_COOPERATIVE,
                        "СПК Фермер",
                        SalesOutletStatus.APPROVED,
                        "Да"
                )
        );
    }

    private record FixtureRow(
            long id,
            String shop,
            String manager,
            String curator,
            String name,
            String inn,
            String headOrganization,
            HeadOrganizationType headOrganizationType,
            String organizationName,
            SalesOutletStatus status,
            String approved
    ) {
    }
}
