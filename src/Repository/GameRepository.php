<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameStatus;
use App\Enum\GameType;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
                ->leftJoin('g.author', 'au')->addSelect('au')
                ->leftJoin('g.hintedPlayers', 'hp', 'WITH', 'hp.id = :userId')
                ->addSelect('COUNT(DISTINCT hp.id) as playerHasHint')
                ->leftJoin(Achievement::class, 'a', 'ON', 'g.id = a.game AND a.player = :userId')
                ->addSelect('a')
                ->addSelect('(SELECT MAX(g2.date) FROM ' . Game::class . ' g2 WHERE g2.type = :type AND g2.status = :status AND g2.date < g.date) as previous_game_date')
                ->addSelect('(SELECT MIN(g3.date) FROM ' . Game::class . ' g3 WHERE g3.type = :type AND g3.status = :status AND g3.date > g.date) as next_game_date')
                ->andWhere('g.type = :type')
                ->andWhere('g.status = :status')
                ->andWhere('g.date <= :date')
                ->groupBy('g.id')
                ->addSelect('CASE WHEN g.date = :date THEN 0 ELSE 1 END as HIDDEN priority')
                ->orderBy('priority', 'ASC')
                ->addOrderBy('g.date', 'DESC')
                ->setParameter('date', $formattedDate)
                ->setParameter('type', $type)
                ->setParameter('status', GameStatus::APPROVED)
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
            ->addSelect('partial a.{id, email}')
            ->leftJoin('g.supervisor', 's')
            ->addSelect('s')
            ->andWhere('g.status = :status')
            ->setParameter('status', GameStatus::APPROVED);

        /** @var array<int, Game> $result */
        $result = $qb->orderBy('g.date', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function countAvailableGames(): int
    {
        return (int)$this->createQueryBuilder('g')
            ->select('COUNT(g.id)')
            ->where('g.date <= :today')
            ->andWhere('g.status = :status')
            ->setParameter('today', new DateTimeImmutable('today'))
            ->setParameter('status', GameStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<GameDifficulty> $difficulties
     * @return list<array{month: string, difficulty: GameDifficulty, total: int, won: int}>
     */
    public function getPlayerStatsByMonthAndDifficulty(User $player, array $difficulties): array
    {
        if (count($difficulties) === 0) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')
            ->select("SUBSTRING(g.date, 1, 7) AS month")
            ->addSelect('g.difficulty')
            ->addSelect('COUNT(g.id) AS total')
            ->addSelect('COUNT(a.id) AS won')
            ->leftJoin(Achievement::class, 'a', 'ON', 'a.game = g AND a.player = :playerId')
            ->where('g.date <= :today')
            ->andWhere('g.status = :status')
            ->setParameter('today', new DateTimeImmutable('today'))
            ->setParameter('status', GameStatus::APPROVED)
            ->setParameter('playerId', $player->getId(), UuidType::NAME)
            ->groupBy('month, g.difficulty')
            ->orderBy('month', 'DESC');

        $this->applyDifficultyFilter($qb, $difficulties);

        /** @var list<array{month: string, difficulty: GameDifficulty, total: int, won: int}> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<GameDifficulty> $difficulties
     * @return list<array{game: Game, won: bool}>
     * @throws \DateMalformedStringException
     */
    public function getPlayerGamesByMonth(User $player, DateTimeImmutable $month, array $difficulties): array
    {
        if (count($difficulties) === 0) {
            return [];
        }

        $qb = $this->createQueryBuilder('g')
            ->select('g AS game')
            ->addSelect('CASE WHEN a.id IS NOT NULL THEN true ELSE false END AS won')
            ->leftJoin(Achievement::class, 'a', 'ON', 'a.game = g AND a.player = :playerId')
            ->where('g.date BETWEEN :start AND :end')
            ->andWhere('g.date <= :today')
            ->andWhere('g.status = :status')
            ->setParameter('start', $month->modify('first day of this month')->format('Y-m-d'))
            ->setParameter('end', $month->modify('last day of this month')->format('Y-m-d'))
            ->setParameter('today', (new DateTimeImmutable('today'))->format('Y-m-d'))
            ->setParameter('status', GameStatus::APPROVED)
            ->setParameter('playerId', $player->getId(), UuidType::NAME)
            ->orderBy('g.date', 'DESC');

        $this->applyDifficultyFilter($qb, $difficulties);

        /** @var list<array{game: Game, won: bool}> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<GameDifficulty> $difficulties
     */
    private function applyDifficultyFilter(QueryBuilder $qb, array $difficulties): void
    {
        if (count($difficulties) > 0) {
            $qb->andWhere('g.difficulty IN (:difficulties)')
                ->setParameter('difficulties', $difficulties);
        }
    }

    /**
     * @return array<Game>
     * @throws \DateMalformedStringException
     */
    public function getNoDraftGamesByMonthAndStatus(?DateTimeImmutable $month = null, ?GameStatus $status = null): array
    {
        $qb = $this->createQueryBuilder('g')
            ->leftJoin('g.author', 'a')->addSelect('a')
            ->leftJoin('g.supervisor', 's')->addSelect('s');

        if ($month !== null) {
            $qb->andWhere('g.date BETWEEN :start AND :end')
                ->setParameter('start', $month->modify('first day of this month')->format('Y-m-d'))
                ->setParameter('end', $month->modify('last day of this month')->format('Y-m-d'))
                ->orderBy('g.date', 'DESC');
        }

        $qb->andWhere('g.status != :excludedStatus')
            ->setParameter('excludedStatus', GameStatus::DRAFT);

        if ($status !== null) {
            $qb->andWhere('g.status = :status')
                ->setParameter('status', $status);
        }

        /** @var array<Game> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<string>
     */
    public function getTakenDates(): array
    {
        /** @var list<string> */
        return $this->createQueryBuilder('g')
            ->select('g.date')
            ->orderBy('g.date', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * @return list<DateTimeImmutable>
     */
    public function getApprovedDatesByType(GameType $type, DateTimeImmutable $startDate, int $limit = 90): array
    {
        /** @var list<DateTimeImmutable> */
        return $this->createQueryBuilder('g')
            ->select('g.date')
            ->where('g.type = :type')
            ->andWhere('g.date >= :start')
            ->andWhere('g.status = :status')
            ->setParameter('type', $type)
            ->setParameter('start', $startDate)
            ->setParameter('status', GameStatus::APPROVED)
            ->orderBy('g.date', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
