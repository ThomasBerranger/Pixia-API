<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
    public function findOrderedByScores(int $max = 0): array
    {
        // Étape 1 : Récupérer les IDs des top users
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

        // Étape 2 : Récupérer les users complets avec leurs badges
        return $this->createQueryBuilder('u')
            ->leftJoin('u.badges', 'b')
            ->addSelect('b')
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
}
