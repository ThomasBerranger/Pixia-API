<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Date\DateRequest;
use App\DTO\Game\GameAdminUpdateRequest;
use App\Entity\Game;
use App\Enum\GameStatus;
use App\Enum\UserRole;
use App\Repository\GameRepository;
use App\Security\Voter\GameVoter;
use App\Service\AuthenticatedUserService;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted(UserRole::ADMIN->value)]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly ImageService             $imageService,
        private readonly GameRepository           $gameRepository,
        private readonly AuthenticatedUserService $authenticatedUserService,
    )
    {
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route(path: '/games', name: 'games_index', methods: ['GET'], format: 'json')]
    public function gamesIndex(
        #[MapQueryString] ?DateRequest $month = null,
        #[MapQueryParameter] ?GameStatus $status = null
    ): JsonResponse
    {
        $games = $this->gameRepository->getNoDraftGamesByMonthAndStatus($month?->getMonth(), $status);

        return $this->json(data: $games, context: ['groups' => ['game:index:admin']]);
    }

    #[Route(path: '/games/taken-dates', name: 'games_taken_dates', methods: ['GET'], format: 'json')]
    public function gamesTakenDates(): JsonResponse
    {
        $dates = $this->gameRepository->getTakenDates();

        return $this->json(data: $dates);
    }

    #[Route(path: '/games/{id}', name: 'games_show', methods: ['GET'], format: 'json')]
    #[IsGranted('view', subject: 'game')]
    public function gameShow(Game $game): JsonResponse
    {
        return $this->json(data: $game, context: ['groups' => ['game:show:admin']]);
    }

    #[Route('/games/{id}', name: 'game_update', methods: ['PATCH'], format: 'json')]
    #[IsGranted('edit', subject: 'game')]
    public function update(Game $game, #[MapRequestPayload] GameAdminUpdateRequest $gameUpdateRequest): JsonResponse
    {
        $oldGameDate = $game->getDate();
        $currentUser = $this->authenticatedUserService->getUserOrThrow();

        if ($gameUpdateRequest->assignToMe === true) {
            $this->denyAccessUnlessGranted(GameVoter::SUPERVISE, $game);
            $game->setSupervisor($currentUser);
        }

        if ($gameUpdateRequest->status !== null) {
            $this->denyAccessUnlessGranted(GameVoter::APPROVE_OR_REJECT, $game);
        }

        $gameUpdateRequest->updateGame($game);

        if ($game->getImage() && $gameUpdateRequest->date !== null) {
            $this->imageService->move($oldGameDate, $game->getType(), $game->getDate(), $game->getImage());
        }

        $this->entityManager->flush();

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
}
