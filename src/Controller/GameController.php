<?php

namespace App\Controller;

use App\DTO\GameAnswerRequest;
use App\DTO\GameDifficultyRequest;
use App\DTO\GameShowRequest;
use App\DTO\GameUpdateImageRequest;
use App\DTO\GameUpdateRequest;
use App\Entity\Game;
use App\Service\GameAnswerService;
use App\Service\AchievementService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/games', name: 'game_')]
final class GameController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameAnswerService      $gameAnswerService,
        private readonly AchievementService     $achievementService,
        private readonly ImageService           $imageService)
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

    #[Route('/daily', name: 'daily', methods: ['GET'], format: 'json')]
    public function daily(#[MapQueryString] GameShowRequest $request): JsonResponse
    {
        $gameWithUserAchievement = $this->entityManager->getRepository(Game::class)->findGameWithUserAchievement(
            $request->getDate(), $request->type, $this->getCurrentUser()
        );

        return $this->json(data: $gameWithUserAchievement, context: [
            'groups' => ['game:show', $gameWithUserAchievement['achievement'] ? 'game:solution' : null],
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], format: 'json')]
    #[IsGranted('view', subject: 'game')]
    public function show(Game $game): JsonResponse
    {
        return $this->json(data: $game, context: ['groups' => ['game:admin']]);
    }

    #[Route('', name: 'index', methods: ['GET'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): JsonResponse
    {
        $games = $this->entityManager->getRepository(Game::class)->findGamesWithAuthor();

        return $this->json(data: $games, context: ['groups' => 'game:admin']);
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
        $result = $this->gameAnswerService->validateAnswer($game, $this->getCurrentUser(), $request->answer);

        return $this->json(data: $result, context: [
            'groups' => ['game:solution']
        ]);
    }

    #[Route('/{id}/difficulty', name: 'difficulty', methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function difficulty(Game $game, #[MapRequestPayload] GameDifficultyRequest $difficultyRequest): JsonResponse
    {
        $history = $this->achievementService->saveDifficultyVote($game, $difficultyRequest->difficulty);

        return $this->json(data: $history, context: ['groups' => ['game:solution']]);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function update(Game $game, #[MapRequestPayload] GameUpdateRequest $updatedGame): JsonResponse
    {
        $game->setSolution($updatedGame->solution);
        $game->setHint($updatedGame->hint);
        $game->setDate($updatedGame->date);
        $game->setDifficulty($updatedGame->difficulty);
        $game->setExplanation($updatedGame->explanation);

        $this->entityManager->flush();

        return $this->json(data: $game, context: ['groups' => ['game:admin']]);
    }

    #[Route('/{id}/image', name: 'update_image', methods: ['POST'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function updateImage(Game $game, Request $request, ValidatorInterface $validator): JsonResponse
    {
        $dto = new GameUpdateImageRequest();
        $dto->image = $request->files->get('image');

        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], 400);
        }

        $image = $this->imageService->store($game->getDate(), $game->getType(), $dto->image);

        $game->setImage($image);

        $this->entityManager->flush();

        return $this->json(data: $game, context: ['groups' => ['game:admin']]);
    }
}
