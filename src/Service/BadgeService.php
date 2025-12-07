<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\BadgeName;
use Doctrine\ORM\EntityManagerInterface;

readonly class BadgeService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function rewardWin(User $user): void
    {
        $totalWins = $this->entityManager
            ->getRepository(Achievement::class)
            ->count(['player' => $user]);

        $winsBadge = match (true) {
            $totalWins >= 300 => BadgeName::WINS_300,
            $totalWins >= 100 => BadgeName::WINS_100,
            $totalWins >= 50 => BadgeName::WINS_50,
            $totalWins >= 10 => BadgeName::WINS_10,
            $totalWins >= 1 => BadgeName::WINS_1,
            default => null,
        };

        if ($winsBadge && !$this->userHasBadge($user, [$winsBadge])) {
            $newBadge = new Badge();
            $newBadge->setName($winsBadge)->setPlayer($user);

            $this->entityManager->persist($newBadge);
        }

        if ($this->entityManager->getRepository(User::class)->getRank($user) <= 10 && !$this->userHasBadge($user, [BadgeName::TOP_10_REACHED])) {
            $newBadge = new Badge();
            $newBadge->setName(BadgeName::TOP_10_REACHED)->setPlayer($user);

            $this->entityManager->persist($newBadge);
        }

        if ($totalWins === 10 && $user->getSponsor()) {
            $sponsor = $user->getSponsor();

            $sponsor->setScore($sponsor->getScore() + 5);
        }

        $this->entityManager->flush();
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
