<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\User\LeaderboardRequest;
use App\DTO\User\UpdateRequest;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\MonthlyLeaderboardRepository;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserService;
use App\Service\UserService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'users_')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly AuthenticatedUserService     $authenticatedUserService,
        private readonly JWTTokenManagerInterface     $JWTTokenManager,
        private readonly UserRepository               $userRepository,
        private readonly UserService                  $userService,
        private readonly MonthlyLeaderboardRepository $monthlyLeaderboardRepository,
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route(path: '/monthly-leaderboard', name: 'monthly_leaderboard', methods: ['GET'], format: 'json')]
    public function monthlyLeaderboard(#[MapQueryString] LeaderboardRequest $request): JsonResponse
    {
        $currentMonthLeaderboard = $this->userService->getCurrentMonthLeaderboard($request->getDate());

        $player = $this->authenticatedUserService->getUser();
        $player?->setRank($this->monthlyLeaderboardRepository->getMonthlyRank($player, $request->getDate()));

        return $this->json(data: [
            'leaderboard' => $currentMonthLeaderboard,
            'player' => $player?->getRank() > count($currentMonthLeaderboard) ?
                $player->setStreaks([UserRepository::CURRENT_STREAK_KEY => $this->userRepository->getCurrentStreak($player)]) :
                null,
        ], context: ['groups' => ['user:index']]);
    }

    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(#[MapQueryParameter] int $page = 1): JsonResponse
    {
        $leaderboard = $this->userService->getLeaderboard($page);

        return $this->json(data: [
            'leaderboard' => $leaderboard,
            'playersCount' => $this->userRepository->count(),
        ], context: ['groups' => ['user:index']]);
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

    #[Route(name: 'update', methods: ['PATCH'])]
    #[IsGranted(UserRole::USER->value)]
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
}
