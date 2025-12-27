<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\MonthlyLeaderboard;
use App\Entity\User;
use App\Repository\MonthlyLeaderboardRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class MonthlyLeaderboardService
{
    public function __construct(
        private EntityManagerInterface       $entityManager,
        private MonthlyLeaderboardRepository $monthlyLeaderboardRepository,
    )
    {
    }

    public function addMonthlyPoints(User $player, int $points, ?DateTimeImmutable $month = null): void
    {
        /** @var DateTimeImmutable $month */
        $month = ($month ?? new DateTimeImmutable())->modify('first day of this month');

        $monthlyLeaderboard = $this->monthlyLeaderboardRepository->findOneBy(['player' => $player, 'month' => $month]);

        if (!$monthlyLeaderboard) {
            $monthlyLeaderboard = new MonthlyLeaderboard();

            $monthlyLeaderboard
                ->setPlayer($player)
                ->setMonth($month);

            $this->entityManager->persist($monthlyLeaderboard);
        }

        $monthlyLeaderboard->setScore($monthlyLeaderboard->getScore() + $points);
    }
}
