<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Achievement>
 */
class AchievementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Achievement::class);
    }

    /**
     * @return Achievement[]
     */
    public function findLastFor(User $user, int $limit): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.game', 'g')
            ->addSelect('g')
            ->where('a.player = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->setMaxResults($limit)
            ->orderBy('a.date', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
