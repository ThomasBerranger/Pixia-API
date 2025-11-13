<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'user_')]
final class UserController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(name: 'index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:read']);
    }

    #[Route(path: '/leaderboard', name: 'leaderboard', methods: ['GET'], format: 'json')]
    public function leaderboard(): JsonResponse
    {
        return $this->json(data: [
            'leaderboard' => $this->entityManager->getRepository(User::class)->findOrderedByScores(10),
            'currentUser' => $this->getCurrentUser(),
            'currentUserRank' => $this->entityManager->getRepository(User::class)->getRank($this->getCurrentUser()),
        ], context: ['groups' => ['user:read']]);
    }

    #[Route(path: '/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('view', subject: 'user')]
    public function show(User $user): Response
    {
        return $this->json(data: $user, context: ['groups' => 'user:read']);
    }
}
