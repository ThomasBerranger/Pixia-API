<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\DTO\GameAnswerRequest;
use App\DTO\GameDifficultyRequest;
use App\DTO\GameShowRequest;
use App\DTO\GameUpdateRequest;
use App\Entity\Game;
use App\Service\AchievementService;
use App\Service\GameSolutionService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/games', name: 'game_')]
final class GameController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameSolutionService    $gameAnswerService,
        private readonly AchievementService     $achievementService,
        private readonly ImageService           $imageService,
    )
    {
    }

    #[Route('', name: 'create', methods: ['POST'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function create(
        #[MapRequestPayload(
            serializationContext: ['groups' => ['game:create']],
            validationGroups: ['game:create']
        )] Game                $game,
        EntityManagerInterface $em,
    ): JsonResponse
    {
        $em->persist($game);
        $em->flush();

        return $this->json(data: $game, status: Response::HTTP_CREATED, context: [
            'groups' => ['game:read:admin'],
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
        return $this->json(data: $game, context: ['groups' => ['game:read:admin']]);
    }

    #[Route(name: 'index', methods: ['GET'], format: 'json')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(#[MapQueryParameter] ?string $fields): JsonResponse
    {
        $fields = $fields ? explode(',', $fields) : [];

        $games = $this->entityManager->getRepository(Game::class)->findByFields($fields);

        return $this->json(data: $games, context: ['groups' => 'game:read:admin']);
    }

    #[Route('/{id}/hint', name: 'hint', methods: ['GET'], format: 'json')]
    public function hint(Game $game): JsonResponse
    {
        return $this->json(data: $game->getHint());
    }

    #[Route('/{id}/solution', name: 'solution', methods: ['POST'], format: 'json')]
    #[RateLimit(limiterName: 'game_answer')]
    public function solution(
        Game                                   $game,
        #[MapRequestPayload] GameAnswerRequest $gameAnswerRequest,
    ): JsonResponse
    {
        $result = $this->gameAnswerService->save($game, $gameAnswerRequest->answer, $this->getCurrentUser());

        return $this->json(data: $result, context: ['groups' => ['game:solution']]);
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
        $oldGameDate = $game->getDate();

        $game->setSolution($updatedGame->solution);
        $game->setHint($updatedGame->hint);
        $game->setDate($updatedGame->date);
        $game->setDifficulty($updatedGame->difficulty);

        $this->entityManager->flush();

        if (!$game->getImage()) {
            throw new \LogicException('No image found on edited game.');
        }

        $this->imageService->move($oldGameDate, $game->getType(), $game->getDate(), $game->getImage());

        return $this->json(data: $game, context: ['groups' => ['game:read:admin']]);
    }

    #[Route('/{id}/image', name: 'update_image', methods: ['POST'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function updateImage(Game $game, Request $request): JsonResponse
    {
        $newImageFile = $request->files->get('image');

        if (!$newImageFile instanceof UploadedFile) {
            throw new \InvalidArgumentException('Image file is required');
        }

        if (!$this->imageService->validate($newImageFile)) {
            return $this->json('Image non validée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $imageName = $this->imageService->store($game->getType(), $game->getDate(), $game->getImage(), $newImageFile);

        $game->setImage($imageName);

        $this->entityManager->flush();

        return $this->json(
            data: $this->imageService->getDirectoryPath($game->getType(), $game->getDate()) . '/' . $game->getImage(),
            context: ['groups' => ['game:read:admin']]
        );
    }
}
