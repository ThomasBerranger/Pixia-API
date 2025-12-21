<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\User\UpdateRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'users_')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        private readonly UserRepository           $userRepository,
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUser();

        $playerRank = $this->userRepository->getRank($player);

        return $this->json(data: [
            'leaderboard' => $this->userRepository->getLeaderboard(),
            'player' => $playerRank > 10 ? $player : null,
            'playerRank' => $playerRank,
            'playerCurrentStreak' => $playerRank > 10 && $player instanceof User ? $this->userRepository->getCurrentStreak($player) : null,
        ], context: ['groups' => ['user:index']]);
    }

    #[Route(name: 'update', methods: ['PATCH'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function update(
        #[MapRequestPayload] UpdateRequest $updateRequest,
        EntityManagerInterface             $em,
    ): JsonResponse
    {
        $user = $this->authenticatedUserService->getUserOrThrow();

        $updateRequest->update($user);

        $em->flush();

        $token = $this->JWTTokenManager->create($user);

        return $this->json(['token' => $token]);
    }

    #[Route(path: '/{slug:user}', name: 'show', requirements: ['username' => '[a-z0-9àâäéèêëïîôùûüÿæœç_-]+'], methods: ['GET'], format: 'json')]
    public function show(User $user): JsonResponse
    {
        $currentUser = $this->authenticatedUserService->getUser();

        return $this->json(data: [
            'user' => $this->userRepository->findWithBadgesAchievementsGames($user),
            'currentUser' => $currentUser ? $this->userRepository->findWithBadgesAchievementsGames($currentUser) : null,
        ], context: ['groups' => ['user:show']]);
    }
}
