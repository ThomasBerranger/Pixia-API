<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;

readonly class BadgeService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository         $userRepository,
        private UserService           $userService,
    )
    {
    }

    /**
     * @throws Exception
     */
    public function reward(User $player): void
    {
        $totalWins = $this->entityManager
            ->getRepository(Achievement::class)
            ->count(['player' => $player]);

        $this->rewardWin($totalWins, $player);
        $this->rewardTopTenReached($player);
        $this->rewardSponsor($totalWins, $player);
        $this->rewardStreak($player);

        $this->entityManager->flush();
    }

    private function rewardWin(int $totalWins, User $player): void
    {
        $winsBadge = match (true) {
            $totalWins >= 300 => BadgeName::WINS_300,
            $totalWins >= 100 => BadgeName::WINS_100,
            $totalWins >= 50 => BadgeName::WINS_50,
            $totalWins >= 10 => BadgeName::WINS_10,
            $totalWins >= 1 => BadgeName::WINS_1,
            default => null,
        };

        if ($winsBadge && !$this->userHasBadge($player, [$winsBadge])) {
            $newBadge = new Badge();
            $newBadge->setName($winsBadge)->setPlayer($player);

            $this->entityManager->persist($newBadge);
        }
    }

    private function rewardTopTenReached(User $player): void
    {
        if ($this->entityManager->getRepository(User::class)->getRank($player) <= 10 && !$this->userHasBadge($player, [BadgeName::TOP_10_REACHED])) {
            $newBadge = new Badge();
            $newBadge->setName(BadgeName::TOP_10_REACHED)->setPlayer($player);

            $this->entityManager->persist($newBadge);
        }
    }

    private function rewardSponsor(int $totalWins, User $player): void
    {
        if ($totalWins === 10 && $player->getSponsor()) {
            $sponsor = $player->getSponsor();

            $this->userService->addPoints($sponsor, 5);
        }
    }

    /**
     * @throws Exception
     */
    private function rewardStreak(User $player): void
    {
        $currentStreak = $this->userRepository->getStreaks($player)['current'];

        $streakBadge = match (true) {
            $currentStreak >= 200 => BadgeName::STREAK_200,
            $currentStreak >= 90 => BadgeName::STREAK_90,
            $currentStreak >= 30 => BadgeName::STREAK_30,
            $currentStreak >= 10 => BadgeName::STREAK_10,
            default => null,
        };

        if ($streakBadge && !$this->userHasBadge($player, [$streakBadge])) {
            $newBadge = new Badge();
            $newBadge->setName($streakBadge)->setPlayer($player);

            $this->entityManager->persist($newBadge);
        }
    }

    /**
     * @param BadgeName[] $badgeNames
     */
    public function userHasBadge(User $user, array $badgeNames): bool
    {
        return $user->getBadges()->exists(
            fn ($key, Badge $badge) => in_array($badge->getName(), $badgeNames, true)
        );
    }
}
