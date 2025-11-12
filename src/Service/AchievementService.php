<?php

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

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
            ->setPlayer($this->security->getUser() instanceof User ? $this->security->getUser() : null)
            ->setDate(new \DateTime())
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
