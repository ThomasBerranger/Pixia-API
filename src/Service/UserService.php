<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function addPoints(User $user, Game $game): void
    {
        $points = $game->getDifficulty()->getPoints($user->getHintedGames()->contains($game));

        $user->setScore($user->getScore() + $points);

        $this->entityManager->flush();
    }
}
