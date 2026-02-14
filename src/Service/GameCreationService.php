<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\GameType;
use App\Repository\GameRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class GameCreationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameSolutionService $gameAnswerService,
        private GameRepository $gameRepository
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

    /**
     * Calcule la prochaine date disponible pour un type de game donné.
     * Part de +1 semaine et trouve le premier slot libre (pas de game APPROVED).
     */
    public function getNextAvailableDate(GameType $type): DateTimeImmutable
    {
        $startDate = new DateTimeImmutable('+1 week')->setTime(0, 0);

        // Récupérer les dates APPROVED du même type (une seule query optimisée)
        $takenDates = $this->gameRepository->createQueryBuilder('g')
            ->select('g.date')
            ->where('g.type = :type')
            ->andWhere('g.date >= :start')
            ->andWhere('g.status = :status')
            ->setParameter('type', $type)
            ->setParameter('start', $startDate)
            ->setParameter('status', GameStatus::APPROVED)
            ->orderBy('g.date', 'ASC')
            ->setMaxResults(90)
            ->getQuery()
            ->getSingleColumnResult();

        // Trouver le premier gap (slot libre)
        $currentDate = $startDate;
        foreach ($takenDates as $takenDate) {
            if ($currentDate < $takenDate) {
                return $currentDate; // Gap trouvé !
            }
            $currentDate = $takenDate->modify('+1 day');
        }

        // Si toutes les dates sont prises, retourner le jour après la dernière
        return $currentDate;
    }
}
