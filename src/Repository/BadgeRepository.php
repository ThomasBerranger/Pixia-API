<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Badge;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Badge>
 */
class BadgeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Badge::class);
    }

    /**
     * @return list<Badge>
     */
    public function findByPlayerForMonth(User $player, DateTimeImmutable $month): array
    {
        $start = $month->modify('first day of this month')->setTime(0, 0);
        $end = $start->modify('first day of next month');

        /** @var list<Badge> $result */
        $result = $this->createQueryBuilder('b')
            ->where('b.player = :player')
            ->andWhere('b.createdAt >= :start')
            ->andWhere('b.createdAt < :end')
            ->setParameter('player', $player->getId(), UuidType::NAME)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
