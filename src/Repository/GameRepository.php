<?php

namespace App\Repository;

use App\Entity\Game;
use App\Enum\GameType;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Game>
 */
class GameRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Game::class);
    }

    public function findDailyOrPreviousGame(GameType $type = GameType::MULTIVERS): Game
    {
        return $this->createQueryBuilder('g')
            ->andWhere('g.type = :type')
            ->setParameter('date', (new DateTime('today'))->format('Y-m-d'))
            ->setParameter('type', $type)
            ->orderBy('ABS(DATEDIFF(g.date, :date))', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
