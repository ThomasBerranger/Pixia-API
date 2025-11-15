<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/users', name: 'users_')]
final class UserController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(): JsonResponse
    {
        return $this->json(data: [
            'leaderboard' => $this->entityManager->getRepository(User::class)->findOrderedByScores(10),
            'currentUser' => $this->getCurrentUser(),
            'currentUserRank' => $this->entityManager->getRepository(User::class)->getRank($this->getCurrentUser()),
        ], context: ['groups' => ['user:show']]);
    }
}
