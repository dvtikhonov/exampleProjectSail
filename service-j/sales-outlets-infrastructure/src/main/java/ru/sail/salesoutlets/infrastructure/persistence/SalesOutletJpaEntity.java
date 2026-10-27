package ru.sail.salesoutlets.infrastructure.persistence;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.Id;
import jakarta.persistence.Table;
import java.time.Instant;

/**
 * JPA-сущность таблицы {@code sales_outlets}.
 * Схема общая с service-a; миграции здесь не выполняются.
 * Статус и вид ГО хранятся строками (значения enum), как в MySQL ENUM.
 */
@Entity
@Table(name = "sales_outlets")
public class SalesOutletJpaEntity {

    @Id
    @Column(name = "id")
    private Long id;

    @Column(name = "shop", nullable = false)
    private String shop = "";

    @Column(name = "manager", nullable = false)
    private String manager = "";

    @Column(name = "curator", nullable = false)
    private String curator = "";

    @Column(name = "name", nullable = false)
    private String name = "";

    @Column(name = "inn", nullable = false)
    private String inn = "";

    @Column(name = "head_organization", nullable = false)
    private String headOrganization = "";

    @Column(name = "head_organization_type", nullable = false)
    private String headOrganizationType = "ooo";

    @Column(name = "organization_name", nullable = false)
    private String organizationName = "";

    @Column(name = "status", nullable = false)
    private String status = "review";

    @Column(name = "approved", nullable = false, length = 32)
    private String approved = "";

    @Column(name = "user_id")
    private Long userId;

    @Column(name = "created_at", nullable = false)
    private Instant createdAt;

    @Column(name = "updated_at", nullable = false)
    private Instant updatedAt;

    @Column(name = "deleted_at")
    private Instant deletedAt;

    public Long getId() {
        return id;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public String getShop() {
        return shop;
    }

    public void setShop(String shop) {
        this.shop = shop;
    }

    public String getManager() {
        return manager;
    }

    public void setManager(String manager) {
        this.manager = manager;
    }

    public String getCurator() {
        return curator;
    }

    public void setCurator(String curator) {
        this.curator = curator;
    }

    public String getName() {
        return name;
    }

    public void setName(String name) {
        this.name = name;
    }

    public String getInn() {
        return inn;
    }

    public void setInn(String inn) {
        this.inn = inn;
    }

    public String getHeadOrganization() {
        return headOrganization;
    }

    public void setHeadOrganization(String headOrganization) {
        this.headOrganization = headOrganization;
    }

    public String getHeadOrganizationType() {
        return headOrganizationType;
    }

    public void setHeadOrganizationType(String headOrganizationType) {
        this.headOrganizationType = headOrganizationType;
    }

    public String getOrganizationName() {
        return organizationName;
    }

    public void setOrganizationName(String organizationName) {
        this.organizationName = organizationName;
    }

    public String getStatus() {
        return status;
    }

    public void setStatus(String status) {
        this.status = status;
    }

    public String getApproved() {
        return approved;
    }

    public void setApproved(String approved) {
        this.approved = approved;
    }

    public Long getUserId() {
        return userId;
    }

    public void setUserId(Long userId) {
        this.userId = userId;
    }

    public Instant getCreatedAt() {
        return createdAt;
    }

    public void setCreatedAt(Instant createdAt) {
        this.createdAt = createdAt;
    }

    public Instant getUpdatedAt() {
        return updatedAt;
    }

    public void setUpdatedAt(Instant updatedAt) {
        this.updatedAt = updatedAt;
    }

    public Instant getDeletedAt() {
        return deletedAt;
    }

    public void setDeletedAt(Instant deletedAt) {
        this.deletedAt = deletedAt;
    }
}
