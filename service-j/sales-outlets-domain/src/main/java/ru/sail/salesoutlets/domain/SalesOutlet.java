package ru.sail.salesoutlets.domain;

import java.time.Instant;
import java.util.Objects;
import ru.sail.salesoutlets.domain.dto.UpdateHeadOrganizationDto;
import ru.sail.salesoutlets.domain.dto.UpdateSalesOutletDto;
import ru.sail.salesoutlets.domain.enums.HeadOrganizationType;
import ru.sail.salesoutlets.domain.enums.SalesOutletStatus;

/**
 * Агрегат торговой точки (без зависимости от JPA/Spring).
 *
 * <p>Изменение состояния — только через {@link #apply(UpdateSalesOutletDto)},
 * {@link #apply(UpdateHeadOrganizationDto)}, {@link #recordChange(Long, Instant)}
 * и {@link #markDeleted(Long, Instant)}. Репозиторий сохраняет уже изменённый снимок.
 *
 * @param id                    идентификатор
 * @param shop                  магазин
 * @param manager               менеджер
 * @param curator               куратор ТТ
 * @param name                  название ТТ
 * @param inn                   ИНН головной организации
 * @param headOrganization      головная организация
 * @param headOrganizationType  вид ГО
 * @param organizationName      название организации
 * @param status                статус
 * @param approved              поле «Одобрено»
 * @param userId                id последнего gateway-пользователя
 * @param updatedAt             момент последнего изменения
 * @param deletedAt             момент soft delete; {@code null}, если точка активна
 */
public record SalesOutlet(
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
        String approved,
        Long userId,
        Instant updatedAt,
        Instant deletedAt
) {

    /**
     * Применяет полное обновление полей. Автор и метки времени не меняются:
     * их фиксирует {@link #recordChange(Long, Instant)}.
     *
     * @throws IllegalStateException если точка уже удалена
     */
    public SalesOutlet apply(UpdateSalesOutletDto dto) {
        ensureActive();
        Objects.requireNonNull(dto, "dto");
        return copy(
                dto.shop(),
                dto.manager(),
                dto.curator(),
                dto.name(),
                dto.inn(),
                dto.headOrganization(),
                dto.headOrganizationType(),
                dto.organizationName(),
                dto.status(),
                approved,
                userId,
                updatedAt,
                deletedAt
        );
    }

    /**
     * Применяет смену головной организации. Остальные поля не меняются.
     *
     * @throws IllegalStateException если точка уже удалена
     */
    public SalesOutlet apply(UpdateHeadOrganizationDto dto) {
        ensureActive();
        Objects.requireNonNull(dto, "dto");
        return copy(
                shop,
                manager,
                curator,
                name,
                inn,
                dto.headOrganization(),
                dto.headOrganizationType(),
                organizationName,
                status,
                approved,
                userId,
                updatedAt,
                deletedAt
        );
    }

    /**
     * Фиксирует автора и момент изменения. {@code userId == null} оставляет прежнего автора.
     *
     * @throws IllegalStateException если точка уже удалена
     */
    public SalesOutlet recordChange(Long userId, Instant instant) {
        ensureActive();
        Objects.requireNonNull(instant, "instant");
        return copy(
                shop,
                manager,
                curator,
                name,
                inn,
                headOrganization,
                headOrganizationType,
                organizationName,
                status,
                approved,
                resolveUserId(userId),
                instant,
                deletedAt
        );
    }

    /**
     * Помечает точку удалённой: автор, {@code updatedAt} и {@code deletedAt} становятся {@code instant}.
     * {@code userId == null} оставляет прежнего автора.
     *
     * @throws IllegalStateException если точка уже удалена
     */
    public SalesOutlet markDeleted(Long userId, Instant instant) {
        ensureActive();
        Objects.requireNonNull(instant, "instant");
        return copy(
                shop,
                manager,
                curator,
                name,
                inn,
                headOrganization,
                headOrganizationType,
                organizationName,
                status,
                approved,
                resolveUserId(userId),
                instant,
                instant
        );
    }

    private Long resolveUserId(Long userId) {
        return userId != null ? userId : this.userId;
    }

    private void ensureActive() {
        if (deletedAt != null) {
            throw new IllegalStateException("Sales outlet " + id + " is deleted.");
        }
    }

    private SalesOutlet copy(
            String shop,
            String manager,
            String curator,
            String name,
            String inn,
            String headOrganization,
            HeadOrganizationType headOrganizationType,
            String organizationName,
            SalesOutletStatus status,
            String approved,
            Long userId,
            Instant updatedAt,
            Instant deletedAt
    ) {
        return new SalesOutlet(
                id,
                shop,
                manager,
                curator,
                name,
                inn,
                headOrganization,
                headOrganizationType,
                organizationName,
                status,
                approved,
                userId,
                updatedAt,
                deletedAt
        );
    }
}
