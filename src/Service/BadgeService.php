<?php

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\Badge as BadgeEnum;
use Doctrine\ORM\EntityManagerInterface;

readonly class BadgeService
{
    private const WINS_BADGES = [
        BadgeEnum::WINS_1,
        BadgeEnum::WINS_10,
        BadgeEnum::WINS_50,
        BadgeEnum::WINS_100,
        BadgeEnum::WINS_300,
    ];

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return Badge[]
     */
    public function checkAndAwardBadges(User $user): void
    {
        $totalWins = $this->entityManager
            ->getRepository(Achievement::class)
            ->count(['player' => $user]);

        $earnedBadge = match (true) {
            $totalWins >= 300 => BadgeEnum::WINS_300,
            $totalWins >= 100 => BadgeEnum::WINS_100,
            $totalWins >= 50 => BadgeEnum::WINS_50,
            $totalWins >= 10 => BadgeEnum::WINS_10,
            $totalWins >= 1 => BadgeEnum::WINS_1,
            default => null,
        };

        if (!$earnedBadge) {
            return;
        }

        $hasBadge = $user->getBadges()->exists(
            fn($key, Badge $badge) => $badge->getName() === $earnedBadge
        );

        if ($hasBadge) {
            return;
        }

        foreach ($user->getBadges() as $badge) {
            if (in_array($badge->getName(), self::WINS_BADGES, true)) {
                $user->removeBadge($badge);
            }
        }

        $newBadge = new Badge();
        $newBadge->setName($earnedBadge);
        $user->addBadge($newBadge);

        $this->entityManager->persist($newBadge);
        $this->entityManager->flush();
    }
}