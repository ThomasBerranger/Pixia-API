<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Repository\AchievementRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class AchievementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AchievementRepository $achievementRepository,
    )
    {
    }

    public function saveResult(User $player, Game $game): void
    {
        $achievement = new Achievement();

        $achievement
            ->setPlayer($player)
            ->setGame($game)
            ->setDate(new DateTimeImmutable())
            ->setDifficulty(GameDifficulty::EASY);

        $this->entityManager->persist($achievement);
    }

    /**
     * @return list<array{date: string, points: int}>
     * @throws Exception
     */
    public function getRecentPointsHistory(User $player, int $days = 14): array
    {
        $rows = $this->achievementRepository->getRecentHistory($player, $days);

        $pointsByDate = [];
        foreach ($rows as $row) {
            $points = 0;

            if ((int) $row['won'] === 1) {
                $difficulty = GameDifficulty::from($row['difficulty']);
                $points = $difficulty->getPoints((int) $row['hinted'] === 1);
            }

            $date = $row['date'];
            $pointsByDate[$date] = ($pointsByDate[$date] ?? 0) + $points;
        }

        $result = [];
        $today = new DateTimeImmutable('today');

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $today->modify(sprintf('-%d days', $i))->format('Y-m-d');
            $result[] = [
                'date' => $date,
                'points' => $pointsByDate[$date] ?? 0,
            ];
        }

        return $result;
    }
}
