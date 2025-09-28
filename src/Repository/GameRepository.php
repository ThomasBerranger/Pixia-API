<?php

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    /**
     * @param DateTime|null $date
     * @param GameType|null $type
     * @param User|null $user
     * @return array{game: Game|null ,achievement: Achievement|null, has_previous: boolean, has_next: boolean}
     */
    public function findGameWithUserAchievement(?DateTime $date, ?GameType $type, ?User $user): array
    {
        $gameWithUserAchievement =
            $this->createQueryBuilder('g')
                ->leftJoin(Achievement::class, 'a', 'WITH', 'g.id = a.game AND a.player = :userId')
                ->addSelect('a')
                ->addSelect('CASE WHEN EXISTS(SELECT 1 FROM ' . Game::class . ' g2 WHERE g2.type = :type AND g2.date < :date) THEN 1 ELSE 0 END as has_previous')
                ->addSelect('CASE WHEN EXISTS(SELECT 1 FROM ' . Game::class . ' g3 WHERE g3.type = :type AND g3.date > :date) THEN 1 ELSE 0 END as has_next')
                ->andWhere('g.type = :type')
                ->andWhere('g.date <= :date')
                ->groupBy('g.id')
                ->addSelect('CASE WHEN g.date = :date THEN 0 ELSE 1 END as HIDDEN priority')
                ->orderBy('priority', 'ASC')
                ->addOrderBy('g.date', 'DESC')
                ->setParameter('date', $date->format('Y-m-d'))
                ->setParameter('type', $type)
                ->setParameter('userId', $user->getId(), UuidType::NAME)
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

        return isset($gameWithUserAchievement[0]) ? [
            'game' => $gameWithUserAchievement[0],
            'achievement' => $gameWithUserAchievement[1][0],
            'has_previous' => $gameWithUserAchievement[1]['has_previous'],
            'next_previous' => $gameWithUserAchievement[1]['has_next'],
        ] : [
            'game' => null,
            'achievement' => null,
            'has_previous' => false,
            'has_next' => false,
        ];
    }
}
