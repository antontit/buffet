<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Stack;
use App\Entity\Shelf;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class StackRepository extends ServiceEntityRepository
{
    private const SQLSTATE_EXCLUSION_VIOLATION = '23P01';

    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Stack::class);
    }

    /**
     * @return list<Stack>
     */
    public function findByShelfWithDish(Shelf $shelf): array {
        return $this->createQueryBuilder('s')
                    ->leftJoin('s.dish', 'd')
                    ->addSelect('d')
                    ->where('s.shelf = :shelf')
                    ->setParameter('shelf', $shelf)
                    ->orderBy('s.id', 'ASC')
                    ->getQuery()
                    ->getResult();
    }

    /**
     * @return list<Stack>
     */
    public function findAllWithDish(): array {
        return $this->createQueryBuilder('s')
                    ->leftJoin('s.dish', 'd')
                    ->addSelect('d')
                    ->leftJoin('s.shelf', 'sh')
                    ->addSelect('sh')
                    ->orderBy('s.id', 'ASC')
                    ->getQuery()
                    ->getResult();
    }

    /**
     * @return array{movedCount:int, sourceRemaining:int, target:Stack, source:?Stack}
     */
    public function mergeStacks(int $movedCount, int $sourceRemaining, Stack $source, Stack $target): array {
        if ($sourceRemaining <= 0) {
            $this->getEntityManager()->remove($source);
            $source = null;
            $sourceRemaining = 0;
        } else {
            $source->setCount($sourceRemaining);
        }

        $this->getEntityManager()->persist($target);
        if (!is_null($source)) {
            $this->getEntityManager()->persist($source);
        }
        $this->getEntityManager()->flush();

        return [
            'movedCount' => $movedCount,
            'sourceRemaining' => $sourceRemaining,
            'target' => $target,
            'source' => $source,
        ];
    }

    public function detach(object $entity): void {
        $this->getEntityManager()->detach($entity);
    }

    public function save(Stack $stack): void {
        $this->getEntityManager()->persist($stack);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws \Throwable|\App\Exception\CollisionException
     */
    public function saveWithCollisionCheck(Stack $stack): void {
        try {
            $this->save($stack);
        } catch (\Throwable $exception) {
            if ($this->isCollisionException($exception)) {
                throw new \App\Exception\CollisionException('Stack collides with existing items.', 0, $exception);
            }

            throw $exception;
        }
    }

    private function isCollisionException(\Throwable $exception): bool {
        if (
            $exception instanceof \Doctrine\DBAL\Exception
            && $exception->getSQLSTATE() === self::SQLSTATE_EXCLUSION_VIOLATION
        ) {
            return true;
        }

        $previous = $exception->getPrevious();
        if ($previous instanceof \Throwable) {
            return $this->isCollisionException($previous);
        }

        return false;
    }

    public function remove(Stack $stack): void {
        $this->getEntityManager()->remove($stack);
        $this->getEntityManager()->flush();
    }
}
