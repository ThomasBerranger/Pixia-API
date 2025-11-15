<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Enum\GameDifficulty;
use Doctrine\ORM\EntityManagerInterface;

readonly class AchievementService
{
    public function __construct(private AuthenticatedUserService $authenticatedUserService, private EntityManagerInterface $entityManager)
    {
    }

    public function saveResult(Game $game): Achievement
    {
        $achievement = new Achievement();

        $achievement
            ->setGame($game)
            ->setPlayer($this->authenticatedUserService->getUserOrThrow())
            ->setDate(new \DateTime())
            ->setDifficulty(GameDifficulty::EASY);

        $this->entityManager->persist($achievement);
        $this->entityManager->flush();

        return $achievement;
    }
}
