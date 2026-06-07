<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Enum\UserRole;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public const string CURRENT_STREAK_KEY = 'current';
    public const string BEST_STREAK_KEY = 'best';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);

        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function getRank(User $player): ?int
    {
        return (int)$this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u2.id) + 1')
            ->from(User::class, 'u2')
            ->where('u2.score > :userScore OR (u2.score = :userScore AND u2.createdAt < :userCreatedAt)')
            ->setParameter('userScore', $player->getScore())
            ->setParameter('userCreatedAt', $player->getCreatedAt())
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findWithBadgesAchievementsGames(User $user): ?User
    {
        /** @var User|null $user */
        $user = $this->createQueryBuilder('u')
            ->leftJoin('u.badges', 'b')
            ->leftJoin('u.achievements', 'a')
            ->leftJoin('a.game', 'g')
            ->addSelect('b')
            ->addSelect('a')
            ->addSelect('g')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $user;
    }

    /**
     * @return array<string, int>
     * @throws Exception
     */
    public function getStreaks(User $player): array
    {
        $sql = "
            WITH games_achievements AS (
                SELECT 
                    g.id,
                    g.date,
                    CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as won,
                    ROW_NUMBER() OVER (ORDER BY g.date DESC) as game_num
                FROM game g
                LEFT JOIN achievement a 
                    ON a.game_id = g.id 
                    AND a.player_id = :playerId
                WHERE g.date <= CURDATE()
                    AND g.status = 'APPROVED'
            ),
            streak_groups AS (
                SELECT 
                    *,
                    game_num - ROW_NUMBER() OVER (
                        PARTITION BY won 
                        ORDER BY game_num
                    ) as streak_id
                FROM games_achievements
            ),
            all_streaks AS (
                SELECT 
                    streak_id,
                    COUNT(*) as streak_length,
                    MIN(game_num) as min_game_num
                FROM streak_groups
                WHERE won = 1
                GROUP BY streak_id
            )
            SELECT 
                COALESCE(MAX(CASE WHEN min_game_num <= 2 THEN streak_length END), 0) as " . self::CURRENT_STREAK_KEY . ",
                COALESCE(MAX(streak_length), 0) as " . self::BEST_STREAK_KEY . "
            FROM all_streaks
        ";

        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, [
            'playerId' => $player->getId()
        ], [
            'playerId' => UuidType::NAME
        ])->fetchAssociative();

        /** @var array{current: int|numeric-string, best: int|numeric-string} $result */
        return [
            self::CURRENT_STREAK_KEY => (int)($result[self::CURRENT_STREAK_KEY]),
            self::BEST_STREAK_KEY => (int)($result[self::BEST_STREAK_KEY]),
        ];
    }

    /**
     * @throws Exception
     */
    public function getCurrentStreak(User $player): int
    {
        return $this->getStreaks($player)[self::CURRENT_STREAK_KEY];
    }

    /**
     * @return list<User>
     */
    public function getMonthlyLeaderboard(DateTimeImmutable $date): array
    {
        $month = $date->modify('first day of this month')->format('Y-m-d');

        /** @var list<string> $userIds */
        $userIds = $this->createQueryBuilder('u')
            ->select('u.id')
            ->join('u.monthlyLeaderboards', 'ml')
            ->where('ml.month = :month')
            ->setParameter('month', $month)
            ->orderBy('ml.score', 'DESC')
            ->addOrderBy('ml.updatedAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($userIds)) {
            return [];
        }

        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->leftJoin('u.monthlyLeaderboards', 'ml', 'WITH', 'ml.month = :month')
            ->leftJoin('u.badges', 'b')
            ->addSelect('ml')
            ->addSelect('b')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->setParameter('month', $month)
            ->getQuery()
            ->getResult();

        /** @var list<User> $orderedUsers */
        $orderedUsers = [];
        foreach ($userIds as $id) {
            foreach ($users as $user) {
                if ($user->getId()?->toBinary() === $id) {
                    $orderedUsers[] = $user;
                    break;
                }
            }
        }

        return $orderedUsers;
    }

    /**
     * @return list<User>
     */
    public function getLeaderboard(int $page = 1, int $max = 20): array
    {
        $offset = ($page - 1) * $max;

        /** @var list<string> $userIds */
        $userIds = $this->createQueryBuilder('u')
            ->select('u.id')
            ->orderBy('u.score', 'DESC')
            ->addOrderBy('u.createdAt', 'ASC')
            ->setMaxResults($max)
            ->setFirstResult($offset)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($userIds)) {
            return [];
        }

        /** @var list<User> $users */
        $users = $this->createQueryBuilder('u')
            ->leftJoin('u.badges', 'b')
            ->leftJoin('u.monthlyLeaderboards', 'ml')
            ->addSelect('b')
            ->addSelect('ml')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->getResult();

        /** @var list<User> $orderedUsers */
        $orderedUsers = [];
        foreach ($userIds as $id) {
            foreach ($users as $user) {
                if ($user->getId()?->toBinary() === $id) {
                    $orderedUsers[] = $user;
                    break;
                }
            }
        }

        return $orderedUsers;
    }

    /**
     * @param list<User> $players
     */
    public function setStreaksFor(array $players): void
    {
        if (empty($players)) {
            return;
        }

        $playerIds = array_map(function (User $u): string {
            $id = $u->getId();
            if ($id === null) {
                throw new \RuntimeException('User ID cannot be null');
            }
            return $id->toBinary();
        }, $players);

        $sql = "
            WITH ranked_users AS (
                SELECT player_id
                FROM achievement
                WHERE player_id IN (:playerIds)
                GROUP BY player_id
            ),
            user_games AS (
                SELECT 
                    ru.player_id,
                    g.date,
                    CASE WHEN a.id IS NOT NULL THEN 1 ELSE 0 END as won,
                    ROW_NUMBER() OVER (PARTITION BY ru.player_id ORDER BY g.date DESC) as game_num
                FROM ranked_users ru
                CROSS JOIN game g
                LEFT JOIN achievement a ON a.game_id = g.id AND a.player_id = ru.player_id
                WHERE g.date <= CURDATE()
                    AND g.status = 'APPROVED'
            ),
            streak_calc AS (
                SELECT 
                    player_id,
                    game_num - ROW_NUMBER() OVER (PARTITION BY player_id, won ORDER BY game_num) as streak_id,
                    won,
                    game_num
                FROM user_games
            ),
            final_streaks AS (
                SELECT 
                    player_id,
                    COUNT(*) as streak_length,
                    MIN(game_num) as min_game_num
                FROM streak_calc
                WHERE won = 1
                GROUP BY player_id, streak_id
            )
            SELECT 
                player_id,
                COALESCE(MAX(CASE WHEN min_game_num <= 2 THEN streak_length END), 0) as " . self::CURRENT_STREAK_KEY . "
            FROM final_streaks
            GROUP BY player_id
        ";

        $streaks = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'playerIds' => $playerIds
            ], [
                'playerIds' => ArrayParameterType::BINARY
            ])
            ->fetchAllAssociative();

        /** @var array<string, int> $streaksByPlayerId */
        $streaksByPlayerId = [];
        foreach ($streaks as $streak) {
            $playerId = $streak['player_id'] ?? null;
            $streakValue = $streak[self::CURRENT_STREAK_KEY] ?? 0;

            if (is_string($playerId) && is_numeric($streakValue)) {
                $streaksByPlayerId[$playerId] = (int)$streakValue;
            }
        }

        foreach ($players as $player) {
            $player->setStreaks([
                self::CURRENT_STREAK_KEY => $streaksByPlayerId[$player->getId()?->toBinary() ?? ''] ?? 0,
                self::BEST_STREAK_KEY => 0
            ]);
        }
    }

    public function findForDashboard(User $user): User
    {
        /** @var User */
        return $this->createQueryBuilder('u')
            ->leftJoin('u.badges', 'b')->addSelect('b')
            ->leftJoin('u.contributions', 'c')->addSelect('c')
            ->leftJoin('u.monthlyLeaderboards', 'ml')->addSelect('ml')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId(), UuidType::NAME)
            ->getQuery()
            ->getSingleResult();
    }

    public function countReferees(User $sponsor): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.sponsor = :sponsor')
            ->setParameter('sponsor', $sponsor)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return User[] */
    public function findByRole(string $role): array
    {
        /** @var array<User> $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Recipients for the monthly recap email: users who gave email consent and are not bots or banned.
     *
     * @return list<User>
     */
    public function findEmailNotificationRecipients(): array
    {
        /** @var list<User> $result */
        $result = $this->createQueryBuilder('u')
            ->where('u.emailConsent = true')
            ->andWhere('u.roles NOT LIKE :bot')
            ->andWhere('u.roles NOT LIKE :banned')
            ->setParameter('bot', '%"' . UserRole::BOT->value . '"%')
            ->setParameter('banned', '%"' . UserRole::BANNED->value . '"%')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
