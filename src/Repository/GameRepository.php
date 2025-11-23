<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
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
     * @return array{game: Game, achievement: Achievement|null|Game, previousGameDate: mixed, nextGameDate: mixed}
     * @throws Exception
     */
    public function findGameWithUserAchievement(?\DateTime $date, ?GameType $type, ?User $user): array
    {
        $formattedDate = $date ? $date->format('Y-m-d') : (new \DateTime('now'))->format('Y-m-d');

        $gameWithUserAchievement =
            $this->createQueryBuilder('g')
                ->leftJoin('g.hintedPlayers', 'hp', 'WITH', 'hp.id = :userId')
                ->addSelect('hp')
                ->leftJoin(Achievement::class, 'a', 'WITH', 'g.id = a.game AND a.player = :userId')
                ->addSelect('a')
                ->addSelect('(SELECT MAX(g2.date) FROM ' . Game::class . ' g2 WHERE g2.type = :type AND g2.date < g.date) as previous_game_date')
                ->addSelect('(SELECT MIN(g3.date) FROM ' . Game::class . ' g3 WHERE g3.type = :type AND g3.date > g.date) as next_game_date')
                ->andWhere('g.type = :type')
                ->andWhere('g.date <= :date')
                ->groupBy('g.id')
                ->addSelect('CASE WHEN g.date = :date THEN 0 ELSE 1 END as HIDDEN priority')
                ->orderBy('priority', 'ASC')
                ->addGroupBy('hp.id')
                ->addOrderBy('g.date', 'DESC')
                ->setParameter('date', $formattedDate)
                ->setParameter('type', $type)
                ->setParameter('userId', $user?->getId(), UuidType::NAME)
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

        if (empty($gameWithUserAchievement) || !$gameWithUserAchievement[0][0] instanceof Game) {
            throw new Exception('Game not found');
        }

        return [
            'game' => $gameWithUserAchievement[0][0],
            'achievement' => $gameWithUserAchievement[1][0],
            'previousGameDate' => $gameWithUserAchievement[1]['previous_game_date'] ?? false,
            'nextGameDate' => $gameWithUserAchievement[1]['next_game_date'] ?? false,
        ];
    }

    /**
     * @param array<int, string> $fields
     *
     * @return array<int, Game>
     */
    public function findByFields(array $fields = []): array
    {
        $qb = $this->createQueryBuilder('g');

        if (!empty($fields)) {
            array_unshift($fields, 'id');

            $fieldsStr = implode(', ', $fields);
            $qb->select('partial g.{' . $fieldsStr . '}');
        } else {
            $qb->select('g');
        }

        $qb->leftJoin('g.author', 'a')
            ->addSelect('partial a.{id, email}');

        /** @var array<int, Game> $result */
        $result = $qb->orderBy('g.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countAvailableGames(): int
    {
        return (int) $this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.date <= :today')
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }
}
