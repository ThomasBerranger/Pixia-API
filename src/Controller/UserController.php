<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\AuthenticatedUserService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'users_')]
final class UserController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService,
    )
    {
    }

    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(): JsonResponse
    {
        return $this->json(data: [
            'leaderboard' => $this->entityManager->getRepository(User::class)->findOrderedByScores(10),
            'currentUser' => $this->authenticatedUserService->getUser(),
            'currentUserRank' => $this->entityManager->getRepository(User::class)->getRank($this->authenticatedUserService->getUser()),
        ], context: ['groups' => ['user:show']]);
    }
}
