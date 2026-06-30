<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesOutlet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Doctrine-репозиторий сущности SalesOutlet (Entity).
 *
 * @extends ServiceEntityRepository<SalesOutlet>
 */
class SalesOutletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesOutlet::class);
    }
}
