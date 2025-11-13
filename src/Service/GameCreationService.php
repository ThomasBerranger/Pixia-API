<?php

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

readonly class GameCreationService
{
    public function __construct(private EntityManagerInterface $entityManager, private GameSolutionService $gameAnswerService)
    {
    }

    public function gameSolutionExist(string $solution): string|bool
    {
        $gamesSolutions = array_column($this->entityManager->getRepository(Game::class)->findByFields(['solution']), 'solution');

        foreach ($gamesSolutions as $gameSolution) {
            if ($this->gameAnswerService->wordsMatch($gameSolution, $solution)) {
                return $gameSolution;
            }
        }

        return false;
    }
}
