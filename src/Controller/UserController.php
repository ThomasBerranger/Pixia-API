<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/users', name: 'user_')]
final class UserController extends BaseController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): Response
    {
        return $this->json(data: $this->getUser(), context: ['groups' => 'user:read']);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    #[IsGranted('view', subject: 'user')]
    public function show(User $user): Response
    {
        return $this->json(data: $user, context: ['groups' => 'user:read']);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:read']);
    }
}
