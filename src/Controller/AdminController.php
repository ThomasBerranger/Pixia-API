<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\Contribution\ContributionUpdateRequest;
use App\DTO\Game\GameCreateRequest;
use App\DTO\Game\GameUpdateRequest;
use App\Entity\Contribution;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\ContributionStatus;
use App\Service\ContributionService;
use App\Service\GameCreationService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameCreationService    $gameCreationService,
        private readonly ImageService           $imageService,
        private readonly ContributionService    $contributionService,
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
        $oldGameDate = $game->getDate();

        $game->setSolution($updatedGame->solution);
        $game->setEnglishSolution($updatedGame->englishSolution);
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
        $imageFile = $request->files->get('image');

        $imageName = $this->imageService->store($game->getType(), $game->getDate(), $game->getImage(), $imageFile);

        $game->setImage($imageName);

        $this->entityManager->flush();

        return $this->json(
            data: $this->imageService->getDirectoryPath($game->getType(), $game->getDate()) . '/' . $game->getImage(),
        );
    }

    #[Route(path: '/contributions', name: 'contributions_index', methods: ['GET'], format: 'json')]
    public function contributionIndex(): JsonResponse
    {
        return $this->json(data: $this->entityManager->getRepository(Contribution::class)->findAllWithAuthor());
    }

    #[Route(path: '/contributions/{id}', name: 'contributions_update', methods: ['PATCH'], format: 'json')]
    public function contributionUpdate(Contribution $contribution, #[MapRequestPayload] ContributionUpdateRequest $contributionUpdateRequest): JsonResponse
    {
        $contribution->setStatus($contributionUpdateRequest->status);

        if ($contributionUpdateRequest->status === ContributionStatus::APPROVED) {
            $this->contributionService->rewardPlayer($contribution->getAuthor(), $contribution->getType());
        }

        $this->entityManager->flush();

        return $this->json(data: $contribution, context: ['groups' => ['contribution:show']]);
    }
}
