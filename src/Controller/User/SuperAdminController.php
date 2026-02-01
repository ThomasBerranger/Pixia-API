<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super-admin', name: 'super_admin_')]
#[IsGranted(UserRole::SUPER_ADMIN->value)]
final class SuperAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {}

    #[Route('/impersonate/{email}', name: 'impersonate', methods: ['POST'])]
    public function impersonate(string $email): JsonResponse
    {
        $player = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        $token = $this->jwtManager->create($player);

        return $this->json(['token' => $token]);
    }

    #[Route('/toggle-ban/{email}', name: 'toggle_ban', methods: ['POST'])]
    public function ban(string $email): JsonResponse
    {
        $player = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        $player->setRoles($player->isBanned() ? [] : [UserRole::BANNED->value]);

        $this->entityManager->flush();

        return $this->json($player, context: ['groups' => ['user:show:admin']]);
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function userIndex(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:index:admin']);
    }
}
