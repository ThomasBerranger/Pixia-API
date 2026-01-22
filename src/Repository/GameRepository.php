<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use DateTimeImmutable;
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
     * @return array{game: Game, achievement: Achievement|null|Game, hintRequested: bool, previousGameDate: mixed, nextGameDate: mixed}
     * @throws Exception
     */
    public function findGameWithUserAchievement(?DateTimeImmutable $date, ?GameType $type, ?User $user): array
    {
        $formattedDate = $date ? $date->format('Y-m-d') : new DateTimeImmutable('now')->format('Y-m-d');

        /** @var list<array{0: Game, 1?: Achievement|null, playerHasHint?: int, previous_game_date?: DateTimeImmutable|null, next_game_date?: DateTimeImmutable|null}> $gameWithUserAchievement */
        $gameWithUserAchievement =
            $this->createQueryBuilder('g')
                ->leftJoin('g.hintedPlayers', 'hp', 'WITH', 'hp.id = :userId')
                ->addSelect('COUNT(DISTINCT hp.id) as playerHasHint')
                ->leftJoin(Achievement::class, 'a', 'WITH', 'g.id = a.game AND a.player = :userId')
                ->addSelect('a')
                ->addSelect('(SELECT MAX(g2.date) FROM ' . Game::class . ' g2 WHERE g2.type = :type AND g2.date < g.date) as previous_game_date')
                ->addSelect('(SELECT MIN(g3.date) FROM ' . Game::class . ' g3 WHERE g3.type = :type AND g3.date > g.date) as next_game_date')
                ->andWhere('g.type = :type')
                ->andWhere('g.date <= :date')
                ->groupBy('g.id')
                ->addSelect('CASE WHEN g.date = :date THEN 0 ELSE 1 END as HIDDEN priority')
                ->orderBy('priority', 'ASC')
                ->addOrderBy('g.date', 'DESC')
                ->setParameter('date', $formattedDate)
                ->setParameter('type', $type)
                ->setParameter('userId', $user?->getId(), UuidType::NAME)
                ->setMaxResults(1)
                ->getQuery()
                ->getResult();

        /** @phpstan-ignore instanceof.alwaysTrue */
        if (empty($gameWithUserAchievement) || !$gameWithUserAchievement[0][0] instanceof Game) {
            throw new Exception('Game not found');
        }

        return [
            'game' => $gameWithUserAchievement[0][0],
            'achievement' => $gameWithUserAchievement[1][0],
            'hintRequested' => (bool)($gameWithUserAchievement[1]['playerHasHint'] ?? 0),
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
            ->setParameter('today', new DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{month: string, difficulty: GameDifficulty, total: int, won: int, hinted: int}>
     */
    public function getPlayerStatsByMonthAndDifficulty(User $player): array
    {
        /** @var list<array{month: string, difficulty: GameDifficulty, total: int, won: int, hinted: int}> */
        return $this->createQueryBuilder('g')
            ->select("SUBSTRING(g.date, 1, 7) AS month")
            ->addSelect('g.difficulty')
            ->addSelect('COUNT(g.id) AS total')
            ->addSelect('COUNT(a.id) AS won')
            ->leftJoin(Achievement::class, 'a', 'WITH', 'a.game = g AND a.player = :playerId')
            ->setParameter('playerId', $player->getId(), UuidType::NAME)
            ->groupBy('month, g.difficulty')
            ->orderBy('month', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<array{game: Game, won: bool, hintRequested: bool}>
     * @throws \DateMalformedStringException
     */
    public function getPlayerGamesByMonth(User $player, DateTimeImmutable $month): array
    {
        /** @var list<array{game: Game, won: bool, hintRequested: bool}> */
        return $this->createQueryBuilder('g')
            ->select('g AS game')
            ->addSelect('CASE WHEN a.id IS NOT NULL THEN true ELSE false END AS won')
            ->addSelect('CASE WHEN hp.id IS NOT NULL THEN true ELSE false END AS hintRequested')
            ->leftJoin(Achievement::class, 'a', 'WITH', 'a.game = g AND a.player = :playerId')
            ->leftJoin('g.hintedPlayers', 'hp', 'WITH', 'hp.id = :playerId')
            ->where('g.date BETWEEN :start AND :end')
            ->setParameter('playerId', $player->getId(), UuidType::NAME)
            ->setParameter('start', $month->modify('first day of this month'))
            ->setParameter('end', $month->modify('last day of this month'))
            ->orderBy('g.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
