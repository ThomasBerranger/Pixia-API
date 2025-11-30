<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\User\UpdateRequest;
use App\Entity\User;
use App\Service\AuthenticatedUserService;
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
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly JWTTokenManagerInterface $JWTTokenManager,
    )
    {
    }

    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(): JsonResponse
    {
        $currentUserRank = $this->entityManager->getRepository(User::class)->getRank($this->authenticatedUserService->getUser());

        return $this->json(data: [
            'leaderboard' => $this->entityManager->getRepository(User::class)->findOrderedByScores(10),
            'currentUser' => $currentUserRank > 10 ? $this->authenticatedUserService->getUser() : null,
            'currentUserRank' => $currentUserRank,
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
            'user' => $this->entityManager->getRepository(User::class)->findWithBadgesAchievementsGames($user),
            'currentUser' => $currentUser ? $this->entityManager->getRepository(User::class)->findWithBadgesAchievementsGames($currentUser) : null,
        ], context: ['groups' => ['user:show']]);
    }
}
