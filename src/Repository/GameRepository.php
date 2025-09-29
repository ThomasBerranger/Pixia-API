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
     * @return array{game: Game|null ,achievement: Achievement|null, previous_game_date: string|null, next_game_date: string|null}
     */
    public function findGameWithUserAchievement(?DateTime $date, ?GameType $type, ?User $user): array
    {
        $gameWithUserAchievement =
            $this->createQueryBuilder('g')
                ->leftJoin(Achievement::class, 'a', 'WITH', 'g.id = a.game AND a.player = :userId')
                ->addSelect('a')
                ->addSelect('(SELECT MAX(g2.date) FROM ' . Game::class . ' g2 WHERE g2.type = :type AND g2.date < :date) as previous_game_date')
                ->addSelect('(SELECT MIN(g3.date) FROM ' . Game::class . ' g3 WHERE g3.type = :type AND g3.date > :date) as next_game_date')
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
            'game' => $gameWithUserAchievement[0][0],
            'achievement' => $gameWithUserAchievement[1][0],
            'previousGameDate' => $gameWithUserAchievement[1]['previous_game_date'],
            'nextGameDate' => $gameWithUserAchievement[1]['next_game_date'],
        ] : [
            'game' => null,
            'achievement' => null,
            'previousGameDate' => false,
            'nextGameDate' => false,
        ];
    }
}
