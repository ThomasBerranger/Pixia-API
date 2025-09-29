<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\Achievement;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class AchievementService
{
    public function __construct(private Security $security, private EntityManagerInterface $entityManager)
    {
    }

    public function saveResult(Game $game): Achievement
    {
        $achievement = new Achievement();

        $achievement
            ->setGame($game)
            ->setPlayer($this->security->getUser())
            ->setDate(new DateTime())
            ->setDifficulty(GameDifficulty::EASY);

        $this->entityManager->persist($achievement);
        $this->entityManager->flush();

        return $achievement;
    }

    public function saveDifficultyVote(Game $game, GameDifficulty $difficulty): Achievement
    {
        $achievement = $this->entityManager->getRepository(Achievement::class)->findOneBy([
            'game' => $game,
            'player' => $this->security->getUser(),
        ]);

        $achievement->setDifficulty($difficulty);

        $this->entityManager->flush();

        return $achievement;
    }
}
