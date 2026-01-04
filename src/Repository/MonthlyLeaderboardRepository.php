<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MonthlyLeaderboard;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonthlyLeaderboard>
 */
class MonthlyLeaderboardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonthlyLeaderboard::class);
    }

    public function getMonthlyRank(User $player, DateTimeImmutable $date): ?int
    {
        $month = $date->modify('first day of this month')->setTime(0, 0);

        $playerML = $this->getEntityManager()
            ->getRepository(MonthlyLeaderboard::class)
            ->findOneBy(['player' => $player, 'month' => $month]);

        if (!$playerML) {
            return null;
        }

        return (int)$this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(DISTINCT ml.id) + 1')
            ->from(MonthlyLeaderboard::class, 'ml')
            ->where('ml.month = :month')
            ->andWhere('ml.score > :score OR (ml.score = :score AND ml.updatedAt < :updatedAt)')
            ->setParameter('month', $month)
            ->setParameter('score', $playerML->getScore())
            ->setParameter('updatedAt', $playerML->getUpdatedAt())
            ->getQuery()
            ->getSingleScalarResult();
    }
}
