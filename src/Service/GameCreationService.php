<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use Doctrine\ORM\EntityManagerInterface;

readonly class GameCreationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameSolutionService $gameAnswerService,
    ) {
    }

    public function gameSolutionExist(string $solution): string|bool
    {
        $existingGames = $this->entityManager->getRepository(Game::class)->findByFields(['solution']);

        foreach ($existingGames as $existingGame) {
            if ($this->gameAnswerService->wordsMatch($existingGame->getSolution(), $solution)) {
                return $existingGame->getSolution();
            }
        }

        return false;
    }

}
