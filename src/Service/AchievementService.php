<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use Doctrine\ORM\EntityManagerInterface;

readonly class AchievementService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function saveResult(User $player, Game $game): void
    {
        $achievement = new Achievement();

        $achievement
            ->setPlayer($player)
            ->setGame($game)
            ->setDate(new \DateTime())
            ->setDifficulty(GameDifficulty::EASY);

        $this->entityManager->persist($achievement);
    }
}
