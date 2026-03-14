<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\User;
use App\Enum\GameStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
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

    public function countByPlayer(User $user): int
    {
        /** @var int $count */
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.player = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleScalarResult();

        return $count;
    }

    /**
     * @return list<array{date: string, difficulty: string, won: string, hinted: string}>
     * @throws Exception
     */
    public function getRecentHistory(User $player, int $days = 14): array
    {
        $startDate = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1))->format('Y-m-d');
        $endDate = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $sql = "
            SELECT g.date, g.difficulty,
                   CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as won,
                   CASE WHEN COUNT(hr.user_id) > 0 THEN 1 ELSE 0 END as hinted
            FROM game g
            LEFT JOIN achievement a ON a.game_id = g.id AND a.player_id = :playerId
            LEFT JOIN hint_request hr ON hr.game_id = g.id AND hr.user_id = :playerId
            WHERE g.date BETWEEN :startDate AND :endDate
              AND g.status = :status
            GROUP BY g.id, g.date, g.difficulty, a.id
            ORDER BY g.date ASC
        ";

        /** @var list<array{date: string, difficulty: string, won: string, hinted: string}> */
        return $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'playerId' => $player->getId(),
                'startDate' => $startDate,
                'endDate' => $endDate,
                'status' => GameStatus::APPROVED->value,
            ], [
                'playerId' => UuidType::NAME,
            ])
            ->fetchAllAssociative();
    }
}
