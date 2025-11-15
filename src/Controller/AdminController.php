<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Game\GameCreateRequest;
use App\DTO\Game\GameUpdateRequest;
use App\Entity\Game;
use App\Entity\User;
use App\Service\GameCreationService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameCreationService    $gameCreationService,
        private readonly ImageService           $imageService,
    )
    {
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function userIndex(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:index:admin']);
    }

    #[Route(path: '/games', name: 'games_create', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload] GameCreateRequest $gameCreateRequest
    ): JsonResponse
    {
        $game = $gameCreateRequest->toGame();

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json(data: $game, status: Response::HTTP_CREATED, context: [
            'groups' => ['game:show:admin'],
        ]);
    }

    #[Route(path: '/games/solution-exist', name: 'games_solution_exist', methods: ['GET'], format: 'json')]
    public function gameSolutionExist(#[MapQueryParameter] string $solution): JsonResponse
    {
        $solutionFound = $this->gameCreationService->gameSolutionExist($solution);

        return $this->json(data: $solutionFound);
    }

    #[Route(path: '/games', name: 'games_index', methods: ['GET'], format: 'json')]
    public function gameIndex(#[MapQueryParameter] ?string $fields): JsonResponse
    {
        $fields = $fields ? explode(',', $fields) : [];

        $games = $this->entityManager->getRepository(Game::class)->findByFields($fields);

        // todo: remove les fields non demandés si $fields is not empty

        return $this->json(data: $games, context: ['groups' => 'game:index:admin']);
    }

    #[Route(path: '/games/{id}', name: 'games_show', methods: ['GET'], format: 'json')]
    #[IsGranted('view', subject: 'game')]
    public function gameShow(Game $game): JsonResponse
    {
        return $this->json(data: $game, context: ['groups' => ['game:show:admin']]);
    }

    #[Route('/games/{id}', name: 'game_update', methods: ['PATCH'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function update(Game $game, #[MapRequestPayload] GameUpdateRequest $updatedGame): JsonResponse
    {
        // todo: refacto cette partie
        $oldGameDate = $game->getDate();

        $game->setSolution($updatedGame->solution);
        $game->setHint($updatedGame->hint);
        $game->setDate($updatedGame->date);
        $game->setDifficulty($updatedGame->difficulty);

        $this->entityManager->flush();

        if ($game->getImage()) {
            $this->imageService->move($oldGameDate, $game->getType(), $game->getDate(), $game->getImage());
        }

        return $this->json(data: $game, context: ['groups' => ['game:show:admin']]);
    }

    #[Route('/games/{id}/image', name: 'games_update_image', methods: ['POST'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function updateImage(Game $game, Request $request): JsonResponse
    {
        // todo: refacto cette partie
        $newImageFile = $request->files->get('image');

        if (!$newImageFile instanceof UploadedFile) {
            throw new \InvalidArgumentException('Aucune image trouvée.');
        }

        if (!$this->imageService->validate($newImageFile)) {
            return $this->json('Image non validée.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $imageName = $this->imageService->store($game->getType(), $game->getDate(), $game->getImage(), $newImageFile);

        $game->setImage($imageName);

        $this->entityManager->flush();

        return $this->json(
            data: $this->imageService->getDirectoryPath($game->getType(), $game->getDate()) . '/' . $game->getImage(),
        );
    }
}
