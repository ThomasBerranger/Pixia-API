<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findAllByNewestFor(User $player): array
    {
        /** @var array<Notification> $result */
        $result = $this->createQueryBuilder('n')
            ->leftJoin('n.readers', 'r')
            ->addSelect('r')
            ->where('n.player IS NULL OR n.player = :playerId')
            ->setParameter('playerId', $player->getId(), UuidType::NAME)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
