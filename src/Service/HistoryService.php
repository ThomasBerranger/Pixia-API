<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\History;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class HistoryService
{
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
    {
    }

    public function saveResult(Game $game): void
    {
        $this->security->getUser();

        $history = new History();

        $history
            ->setGame($game)
            ->setUser($this->security->getUser())
            ->setDate(new DateTime())
            ->setDifficulty(GameDifficulty::EASY);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
