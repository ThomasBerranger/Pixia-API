<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super-admin', name: 'super_admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class SuperAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/impersonate/{email}', name: 'impersonate', methods: ['POST'])]
    public function impersonate(string $email): JsonResponse
    {
        $targetUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$targetUser) {
            return $this->json(['error' => 'Utilisateur introuvable'], 404);
        }

        $token = $this->jwtManager->create($targetUser);

        return $this->json(['token' => $token]);
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function userIndex(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:index:admin']);
    }
}
