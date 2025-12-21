<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
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
    const string CURRENT_STREAK_KEY = 'current';
    const string BEST_STREAK_KEY = 'best';

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

    /**
     * @return list<User>
     */
    private function findOrderedByScoresWithBadges(int $max = 0): array
    {
        $userIds = $this->createQueryBuilder('u')
            ->select('u.id')
            ->orderBy('u.score', 'DESC')
            ->addOrderBy('u.createdAt', 'ASC')
            ->setMaxResults($max)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($userIds)) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->leftJoin('u.badges', 'b')
            ->leftJoin('u.achievements', 'a')
            ->addSelect('b')
            ->addSelect('a')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $userIds)
            ->orderBy('u.score', 'DESC')
            ->addOrderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getRank(?User $user): ?int
    {
        if (!$user instanceof User) {
            return null;
        }

        return (int)$this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u2.id) + 1')
            ->from(User::class, 'u2')
            ->where('u2.score > :userScore OR (u2.score = :userScore AND u2.createdAt < :userCreatedAt)')
            ->setParameter('userScore', $user->getScore())
            ->setParameter('userCreatedAt', $user->getCreatedAt())
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
    public function getStreaks(User $user): array
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
                    AND a.player_id = :userId
                WHERE g.date <= CURDATE()
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
                COALESCE(MAX(CASE WHEN min_game_num = 1 THEN streak_length END), 0) as " . self::CURRENT_STREAK_KEY . ",
                COALESCE(MAX(streak_length), 0) as " . self::BEST_STREAK_KEY . "
            FROM all_streaks
        ";

        $conn = $this->getEntityManager()->getConnection();
        $result = $conn->executeQuery($sql, [
            'userId' => $user->getId()
        ], [
            'userId' => UuidType::NAME
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
     * @throws Exception
     */
    public function getLeaderboard(): array
    {
        $users = $this->findOrderedByScoresWithBadges(10);

        if (empty($users)) {
            return [];
        }

        $userIds = array_map(function (User $u): string {
            $id = $u->getId();
            if ($id === null) {
                throw new \RuntimeException('User ID cannot be null');
            }
            return $id->toBinary();
        }, $users);

        $sql = "
            WITH ranked_users AS (
                SELECT player_id
                FROM achievement
                WHERE player_id IN (:userIds)
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
                COALESCE(MAX(CASE WHEN min_game_num = 1 THEN streak_length END), 0) as " . self::CURRENT_STREAK_KEY . "
            FROM final_streaks
            GROUP BY player_id
        ";

        $streaks = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, [
                'userIds' => $userIds
            ], [
                'userIds' => ArrayParameterType::BINARY
            ])
            ->fetchAllAssociative();

        /** @var array<string, int> $streaksByUserId */
        $streaksByUserId = [];
        foreach ($streaks as $streak) {
            $playerId = $streak['player_id'] ?? null;
            $streakValue = $streak[self::CURRENT_STREAK_KEY] ?? 0;

            if (is_string($playerId) && is_numeric($streakValue)) {
                $streaksByUserId[$playerId] = (int)$streakValue;
            }
        }

        foreach ($users as $user) {
            $user->setStreaks([
                self::CURRENT_STREAK_KEY => $streaksByUserId[$user->getId()?->toBinary() ?? ''] ?? 0,
                self::BEST_STREAK_KEY => 0
            ]);
        }

        return $users;
    }
}
