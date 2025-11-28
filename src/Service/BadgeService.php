<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\Badge as BadgeEnum;
use Doctrine\ORM\EntityManagerInterface;

readonly class BadgeService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function checkAndAwardBadges(User $user): void
    {
        $totalWins = $this->entityManager
            ->getRepository(Achievement::class)
            ->count(['player' => $user]);

        $winsBadge = match (true) {
            $totalWins >= 300 => BadgeEnum::WINS_300,
            $totalWins >= 100 => BadgeEnum::WINS_100,
            $totalWins >= 50 => BadgeEnum::WINS_50,
            $totalWins >= 10 => BadgeEnum::WINS_10,
            $totalWins >= 1 => BadgeEnum::WINS_1,
            default => null,
        };

        if ($winsBadge && !$this->userHasBadge($user, [$winsBadge])) {
            $newBadge = new Badge();
            $newBadge->setName($winsBadge)->setPlayer($user);

            $this->entityManager->persist($newBadge);
        }

        if ($this->entityManager->getRepository(User::class)->getRank($user) <= 10 && !$this->userHasBadge($user, [BadgeEnum::TOP_10_REACHED])) {
            $newBadge = new Badge();
            $newBadge->setName(BadgeEnum::TOP_10_REACHED)->setPlayer($user);

            $this->entityManager->persist($newBadge);
        }

        $this->entityManager->flush();
    }

    public function checkAndAwardCreatorBadge(User $user): void
    {
        if (!$this->userHasBadge($user, [BadgeEnum::CREATOR])) {
            $creatorBadge = new Badge();
            $creatorBadge->setName(BadgeEnum::CREATOR)->setPlayer($user);

            $this->entityManager->persist($creatorBadge);
            $this->entityManager->flush();
        }
    }

    /**
     * @param BadgeEnum[] $badgeNames
     */
    public function userHasBadge(User $user, array $badgeNames): bool
    {
        return $user->getBadges()->exists(
            fn ($key, Badge $badge) => in_array($badge->getName(), $badgeNames, true)
        );
    }
}
