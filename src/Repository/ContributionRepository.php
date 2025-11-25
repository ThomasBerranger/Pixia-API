<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contribution>
 */
class ContributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contribution::class);
    }

    /**
     * @return array<mixed>
     */
    public function findAllWithAuthor(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c')
            ->addSelect('PARTIAL author.{id, username, email}')
            ->leftJoin('c.author', 'author')
            ->getQuery()
            ->getArrayResult();
    }
}
