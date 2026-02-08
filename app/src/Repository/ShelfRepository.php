<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Shelf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class ShelfRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shelf::class);
    }

    /**
     * @return list<Shelf>
     */
    public function findAllOrderedByIdAsc(): array
    {
        return $this->findBy([], ['id' => 'ASC']);
    }
}
