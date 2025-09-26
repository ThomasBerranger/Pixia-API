<?php

namespace App\Controller;

use App\DTO\GameAnswerRequest;
use App\Entity\Game;
use App\Service\GameAnswerService;
use App\Service\GameService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/games', name: 'game_')]
final class GameController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameService            $gameService,
        private readonly GameAnswerService      $gameAnswerService)
    {
    }

    #[
        Route('', name: 'create', methods: ['POST'], format: 'json')]
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
        $game = $this->entityManager->getRepository(Game::class)->findOneBy([]);

        return $this->json(data: $game, context: [
            'groups' => ['game:show', $this->gameService->hasAlreadyWin($game) ? 'game:solution' : null],
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
}
