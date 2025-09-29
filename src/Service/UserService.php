<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\GameDifficulty;
use Doctrine\ORM\EntityManagerInterface;

readonly class UserService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function addPoints(User $user, GameDifficulty $difficulty): void
    {
        $user->setScore($user->getScore() + $difficulty->getPoints());

        $this->entityManager->flush();
    }
}
