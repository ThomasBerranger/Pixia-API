<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\History;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

readonly class GameService
{
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
    {
    }

    public function hasAlreadyWin(Game $game): bool
    {
        return (bool) $this->entityManager->getRepository(History::class)->findOneBy([
            'game' => $game,
            'user' => $this->security->getUser(),
        ]);
    }
}
