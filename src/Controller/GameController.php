<?php

namespace App\Controller;

use App\DTO\GameAnswerRequest;
use App\Entity\Game;
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
    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly GameService $gameService)
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
        $game = $this->entityManager->getRepository(Game::class)->findOneBy(['date' => new \DateTime('2025/10/01')]);

        return $this->json(data: $game, context: [
            'groups' => ['game:show']
        ]);
    }

    #[Route('/{id}/hint', name: 'hint', methods: ['GET'], format: 'json')]
    public function hint(Game $game): JsonResponse
    {
        return $this->json(data: $game->getHint());
    }

    #[Route('/{id}/answer', name: 'answer', methods: ['POST'], format: 'json')]
    public function answer(
        Game $game,
        #[MapRequestPayload] GameAnswerRequest $request
    ): JsonResponse
    {
        return $this->gameService->validateAnswer($game, $request->answer);
    }
}
