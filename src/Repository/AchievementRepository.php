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
     * @return array{count: int, history: list<Achievement>}
     */
    public function findLastWithCountFor(User $user, int $limit): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.game', 'g')
            ->addSelect('g')
            ->where('a.player = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->orderBy('a.date', 'DESC');

        $countQb = clone $qb;
        /** @var int $count */
        $count = $countQb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<Achievement> $history */
        $history = $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return [
            'count' => $count,
            'history' => $history
        ];
    }
}
