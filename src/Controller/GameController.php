<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\DTO\Game\GameAnswerRequest;
use App\DTO\Game\GameShowRequest;
use App\Entity\Game;
use App\Service\AuthenticatedUserService;
use App\Service\GameSolutionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/games', name: 'games_')]
final class GameController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly GameSolutionService      $gameAnswerService,
        private readonly AuthenticatedUserService $authenticatedUserService,
    )
    {
    }

    #[Route('/daily', name: 'daily', methods: ['GET'], format: 'json')]
    public function daily(#[MapQueryString] GameShowRequest $request): JsonResponse
    {
        $gameWithUserAchievement = $this->entityManager->getRepository(Game::class)->findGameWithUserAchievement(
            $request->getDate(), $request->getType(), $this->authenticatedUserService->getUser()
        );

        return $this->json(data: $gameWithUserAchievement, context: [
            'groups' => [
                'game:show',
                $gameWithUserAchievement['achievement'] ? 'game:solution' : null,
                $gameWithUserAchievement['game']->getHintedPlayers()->count() > 0 ? 'game:hint' : null,
            ],
        ]);
    }

    #[Route('/{id}/hint', name: 'hint', methods: ['GET'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function hint(Game $game): JsonResponse
    {
        $user = $this->authenticatedUserService->getUserOrThrow();
        $user->addHintedGame($game);

        $this->entityManager->flush();

        $hint = $game->getHint();

        return $this->json(data: $hint, context: ['groups' => ['game:hint']]);
    }

    #[Route('/{id}/solution', name: 'solution', methods: ['POST'], format: 'json')]
    #[RateLimit(limiterName: 'game_answer')]
    public function solution(
        Game                                   $game,
        #[MapRequestPayload] GameAnswerRequest $gameAnswerRequest,
    ): JsonResponse
    {
        $result = $this->gameAnswerService->save($game, $gameAnswerRequest->answer, $this->authenticatedUserService->getUser());

        return $this->json(data: $result, context: ['groups' => ['game:solution']]);
    }
}
