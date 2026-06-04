<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\ActivityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Activity>
 */
class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    /**
     * @return array{items: list<Activity>, total: int}
     */
    public function findPaginated(?string $search, int $page, int $perPage): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->leftJoin('a.player', 'p')
            ->addSelect('p')
            ->orderBy('a.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage);

        if ($search !== null && $search !== '') {
            $queryBuilder
                ->andWhere('p.username LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $paginator = new Paginator($queryBuilder->getQuery(), false);

        /** @var list<Activity> $items */
        $items = array_values(iterator_to_array($paginator));

        return [
            'items' => $items,
            'total' => count($paginator),
        ];
    }

    public function findLastByPlayerAndType(User $player, ActivityType $type): ?Activity
    {
        /** @var Activity|null */
        return $this->createQueryBuilder('a')
            ->where('a.player = :player')
            ->andWhere('a.type = :type')
            ->setParameter('player', $player->getId(), UuidType::NAME)
            ->setParameter('type', $type)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function hasClaimedThisMonth(User $player, ActivityType $type): bool
    {
        $now = new \DateTimeImmutable();
        $firstDay = $now->modify('first day of this month midnight');
        $firstDayNextMonth = $firstDay->modify('+1 month');

        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.player = :player')
            ->andWhere('a.type = :type')
            ->andWhere('a.createdAt >= :start')
            ->andWhere('a.createdAt < :end')
            ->setParameter('player', $player->getId(), UuidType::NAME)
            ->setParameter('type', $type)
            ->setParameter('start', $firstDay, Types::DATETIME_IMMUTABLE)
            ->setParameter('end', $firstDayNextMonth, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
