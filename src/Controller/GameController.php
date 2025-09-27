<?php

namespace App\Controller;

use App\DTO\GameAnswerRequest;
use App\DTO\GameDifficultyRequest;
use App\Entity\Game;
use App\Entity\History;
use App\Service\GameAnswerService;
use App\Service\HistoryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/games', name: 'game_')]
final class GameController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameAnswerService      $gameAnswerService,
        private readonly HistoryService         $historyService)
    {
    }

    #[Route('', name: 'create', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        #[MapRequestPayload(
            serializationContext: ['groups' => ['game:create']],
            validationGroups: ['game:create']
        )] Game                $game,
        EntityManagerInterface $em
    ): JsonResponse
    {
        $em->persist($game);
        $em->flush();

        return $this->json(data: $game, status: Response::HTTP_CREATED, context: [
            'groups' => ['game:read']
        ]);
    }

    #[Route('', name: 'show', methods: ['GET'], format: 'json')]
    public function show(): JsonResponse
    {
        $game = $this->entityManager->getRepository(Game::class)->findDailyOrPreviousGame();

        $history = $this->entityManager->getrepository(History::class)->findOneBy([
            'game' => $game,
            'user' => $this->getCurrentUser()
        ]);

        return $this->json(data: ['game' => $game, 'history' => $history], context: [
            'groups' => ['game:show', $history ? 'game:solution' : null],
        ]);
    }

    #[Route('/{id}/hint', name: 'hint', methods: ['GET'], format: 'json')]
    public function hint(Game $game): JsonResponse
    {
        return $this->json(data: $game->getHint());
    }

    #[Route('/{id}/solution', name: 'solution', methods: ['POST'], format: 'json')]
    public function solution(
        Game                                   $game,
        #[MapRequestPayload] GameAnswerRequest $request
    ): JsonResponse
    {
        $result = $this->gameAnswerService->validateAnswer($game, $request->answer);

        return $this->json(data: $result, context: [
            'groups' => ['game:solution']
        ]);
    }

    #[Route('/{id}/difficulty', name: 'difficulty', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function difficulty(Game $game, #[MapRequestPayload] GameDifficultyRequest $difficultyRequest): JsonResponse
    {
        $history = $this->historyService->saveDifficultyVote($game, $difficultyRequest->difficulty);

        return $this->json(data: $history, context: ['groups' => ['game:solution']]);
    }
}
