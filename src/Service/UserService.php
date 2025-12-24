<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class UserService
{
    public function __construct(
        private EntityManagerInterface    $entityManager,
        private MonthlyLeaderboardService $monthlyLeaderboardService,
    )
    {
    }

    public function addGamePoints(User $player, Game $game): void
    {
        $points = $game->getDifficulty()->getPoints($player->getHintedGames()->contains($game));

        $this->addPoints($player, $points, $game->getDate());
    }

    public function addPoints(User $player, int $points, ?DateTimeImmutable $month = null): void
    {
        $player->setScore($player->getScore() + $points);
        $this->monthlyLeaderboardService->addMonthlyPoints($player, $points, $month);
    }

    public function findAndRewardSponsor(string $sponsorSlug): User
    {
        $sponsor = $this->entityManager->getRepository(User::class)->findOneBy(['slug' => $sponsorSlug]);

        if (!$sponsor) {
            throw new UnprocessableEntityHttpException('Sponsor not found');
        }

        $this->addPoints($sponsor, 5);

        $sponsor->addBadge(new Badge(BadgeName::SPONSOR));

        if ($sponsor->getReferees()->count() >= 5) {
            $sponsor->addBadge(new Badge(BadgeName::SPONSOR_5));
        }

        return $sponsor;
    }
}
