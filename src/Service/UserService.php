<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class UserService
{
    public function __construct(
        private EntityManagerInterface    $entityManager,
        private MonthlyLeaderboardService $monthlyLeaderboardService,
        private UserRepository            $userRepository,
    )
    {
    }

    public function addGamePoints(User $player, Game $game): void
    {
        $points = $this->getPointsBasedOnHintRequest($player, $game);

        $this->addPoints($player, $points);
        $this->monthlyLeaderboardService->addMonthlyPoints($player, $points, $game->getDate());
    }

    public function addPoints(User $player, int $points): void
    {
        $player->setScore($player->getScore() + $points);
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

    /**
     * @return list<User>
     */
    public function getLeaderboard(int $page): array
    {
        $players = $this->userRepository->getLeaderboard($page);

        $this->userRepository->setStreaksFor($players);

        return $players;
    }

    /**
     * @return list<User>
     */
    public function getCurrentMonthLeaderboard(DateTimeImmutable $date): array
    {
        $players = $this->userRepository->getMonthlyLeaderboard($date);

        $this->userRepository->setStreaksFor($players);

        return $players;
    }

    public function addGameTokens(User $player, Game $game): void
    {
        $player->addTokens($this->getPointsBasedOnHintRequest($player, $game));
    }

    private function getPointsBasedOnHintRequest(User $player, Game $game): int
    {
        return $game->getDifficulty()->getPoints($player->getHintedGames()->contains($game));
    }
}
