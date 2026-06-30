<?php

declare(strict_types=1);

namespace App\Repository;

use App\Contract\Auth\GatewayUserContextInterface;
use App\Contract\SalesOutlets\SalesOutletRepositoryInterface;
use App\Contract\SalesOutlets\SalesOutletsMetadataRepositoryInterface;
use App\Domain\SalesOutlet;
use App\DTO\SalesOutlets\SalesOutletIndexQueryDto;
use App\DTO\SalesOutlets\SalesOutletPaginatedResultDto;
use App\DTO\SalesOutlets\SalesOutletPaginationDto;
use App\DTO\SalesOutlets\UpdateHeadOrganizationDto;
use App\DTO\SalesOutlets\UpdateSalesOutletDto;
use App\Entity\SalesOutlet as SalesOutletEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Shared\SalesOutletsDomain\DTO\SalesOutletFilterDto;

/**
 * Репозиторий торговых точек на Doctrine ORM.
 * Маппит Entity ↔ доменную модель SalesOutlet.
 */
class DoctrineSalesOutletRepository implements SalesOutletRepositoryInterface
{
    public function __construct(
        private readonly SalesOutletRepository $entityRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SalesOutletsMetadataRepositoryInterface $metadataRepository,
        private readonly DoctrineSalesOutletQueryApplicator $queryApplicator,
        private readonly GatewayUserContextInterface $gatewayUserContext,
    ) {}

    public function findById(int $id): ?SalesOutlet
    {
        $entity = $this->entityRepository->findOneBy([
            'id' => $id,
            'deletedAt' => null,
        ]);

        return $entity !== null ? SalesOutletEntityMapper::toDomain($entity) : null;
    }

    public function paginate(SalesOutletIndexQueryDto $queryDto): SalesOutletPaginatedResultDto
    {
        $queryBuilder = $this->entityRepository->createQueryBuilder('so');

        $this->queryApplicator->apply(
            queryBuilder: $queryBuilder,
            filters: new SalesOutletFilterDto(
                search: $queryDto->search,
                status: $queryDto->status,
                columnFilters: $queryDto->columnFilters,
                sort: $queryDto->sort,
                direction: $queryDto->direction,
            ),
            allowedColumnKeys: $this->metadataRepository->allowedColumnKeys(),
        );

        $countQueryBuilder = clone $queryBuilder;
        $total = (int) $countQueryBuilder
            ->select('COUNT(so.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $entities = $queryBuilder
            ->setFirstResult(($queryDto->page - 1) * $queryDto->perPage)
            ->setMaxResults($queryDto->perPage)
            ->getQuery()
            ->getResult();

        $items = array_map(
            static fn (SalesOutletEntity $entity): SalesOutlet => SalesOutletEntityMapper::toDomain($entity),
            $entities,
        );

        return new SalesOutletPaginatedResultDto(
            items: $items,
            pagination: SalesOutletPaginationDto::fromCounts(
                total: $total,
                perPage: $queryDto->perPage,
                currentPage: $queryDto->page,
            ),
        );
    }

    public function updateHeadOrganization(SalesOutlet $salesOutlet, UpdateHeadOrganizationDto $dto): SalesOutlet
    {
        return $this->persist($salesOutlet, [
            'headOrganization' => $dto->headOrganization,
            'headOrganizationType' => $dto->headOrganizationType,
        ]);
    }

    public function update(SalesOutlet $salesOutlet, UpdateSalesOutletDto $dto): SalesOutlet
    {
        return $this->persist($salesOutlet, [
            'shop' => $dto->shop,
            'manager' => $dto->manager,
            'curator' => $dto->curator,
            'name' => $dto->name,
            'inn' => $dto->inn,
            'headOrganization' => $dto->headOrganization,
            'headOrganizationType' => $dto->headOrganizationType,
            'organizationName' => $dto->organizationName,
            'status' => $dto->status,
        ]);
    }

    public function delete(SalesOutlet $salesOutlet): void
    {
        $entity = $this->resolveEntity($salesOutlet);
        $this->applyGatewayUserId($entity);

        $entity->setDeletedAt(new DateTimeImmutable());
        $entity->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    /** Применяет изменения к Entity и возвращает обновлённую доменную модель. */
    private function persist(SalesOutlet $salesOutlet, array $attributes): SalesOutlet
    {
        $entity = $this->resolveEntity($salesOutlet);

        foreach ($attributes as $property => $value) {
            match ($property) {
                'shop' => $entity->setShop((string) $value),
                'manager' => $entity->setManager((string) $value),
                'curator' => $entity->setCurator((string) $value),
                'name' => $entity->setName((string) $value),
                'inn' => $entity->setInn((string) $value),
                'headOrganization' => $entity->setHeadOrganization((string) $value),
                'headOrganizationType' => $entity->setHeadOrganizationType($value),
                'organizationName' => $entity->setOrganizationName((string) $value),
                'status' => $entity->setStatus($value),
                default => null,
            };
        }

        $this->applyGatewayUserId($entity);
        $entity->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();
        $this->entityManager->refresh($entity);

        return SalesOutletEntityMapper::toDomain($entity);
    }

    /** Загружает Entity по id доменной модели; выбрасывает 404, если запись удалена. */
    private function resolveEntity(SalesOutlet $salesOutlet): SalesOutletEntity
    {
        $entity = $this->entityRepository->findOneBy([
            'id' => $salesOutlet->id,
            'deletedAt' => null,
        ]);

        if ($entity === null) {
            throw $this->createNotFoundException($salesOutlet->id);
        }

        return $entity;
    }

    /** Проставляет user_id текущего gateway-пользователя при изменении записи. */
    private function applyGatewayUserId(SalesOutletEntity $entity): void
    {
        $userId = $this->gatewayUserContext->currentUserId();

        if ($userId === null) {
            return;
        }

        $entity->setUserId($userId);
    }

    private function createNotFoundException(int $id): \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
    {
        return new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException(
            sprintf('Sales outlet with id %d not found.', $id),
        );
    }
}
