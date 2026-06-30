<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SalesOutletRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Shared\SalesOutletsDomain\Enums\HeadOrganizationType;
use Shared\SalesOutletsDomain\Enums\SalesOutletStatus;

/**
 * Doctrine-сущность таблицы sales_outlets.
 * Общая с service-a; миграции выполняются только в service-a.
 */
#[ORM\Entity(repositoryClass: SalesOutletRepository::class)]
#[ORM\Table(name: 'sales_outlets')]
class SalesOutlet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $shop = '';

    #[ORM\Column(length: 255)]
    private string $manager = '';

    #[ORM\Column(length: 255)]
    private string $curator = '';

    #[ORM\Column(length: 255)]
    private string $name = '';

    #[ORM\Column(length: 255)]
    private string $inn = '';

    #[ORM\Column(length: 255)]
    private string $headOrganization = '';

    #[ORM\Column(enumType: HeadOrganizationType::class)]
    private HeadOrganizationType $headOrganizationType = HeadOrganizationType::LimitedLiabilityCompany;

    #[ORM\Column(length: 255)]
    private string $organizationName = '';

    #[ORM\Column(enumType: SalesOutletStatus::class)]
    private SalesOutletStatus $status = SalesOutletStatus::Review;

    #[ORM\Column(length: 32)]
    private string $approved = '';

    #[ORM\Column(nullable: true)]
    private ?int $userId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $deletedAt = null;

    public function __construct()
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): string
    {
        return $this->shop;
    }

    public function setShop(string $shop): self
    {
        $this->shop = $shop;

        return $this;
    }

    public function getManager(): string
    {
        return $this->manager;
    }

    public function setManager(string $manager): self
    {
        $this->manager = $manager;

        return $this;
    }

    public function getCurator(): string
    {
        return $this->curator;
    }

    public function setCurator(string $curator): self
    {
        $this->curator = $curator;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getInn(): string
    {
        return $this->inn;
    }

    public function setInn(string $inn): self
    {
        $this->inn = $inn;

        return $this;
    }

    public function getHeadOrganization(): string
    {
        return $this->headOrganization;
    }

    public function setHeadOrganization(string $headOrganization): self
    {
        $this->headOrganization = $headOrganization;

        return $this;
    }

    public function getHeadOrganizationType(): HeadOrganizationType
    {
        return $this->headOrganizationType;
    }

    public function setHeadOrganizationType(HeadOrganizationType $headOrganizationType): self
    {
        $this->headOrganizationType = $headOrganizationType;

        return $this;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function setOrganizationName(string $organizationName): self
    {
        $this->organizationName = $organizationName;

        return $this;
    }

    public function getStatus(): SalesOutletStatus
    {
        return $this->status;
    }

    public function setStatus(SalesOutletStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getApproved(): string
    {
        return $this->approved;
    }

    public function setApproved(string $approved): self
    {
        $this->approved = $approved;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
