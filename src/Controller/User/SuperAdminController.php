<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\UserRole;
use App\Serializer\GameNormalizer;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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
        $player = $this->getPlayerFor($email);

        $token = $this->jwtManager->create($player);

        return $this->json(['token' => $token]);
    }

    #[Route('/toggle-ban/{email}', name: 'toggle_ban', methods: ['POST'])]
    public function ban(string $email): JsonResponse
    {
        $player = $this->getPlayerFor($email);

        $roles = $player->getRoles();

        if ($player->isBanned()) {
            $roles = array_diff($roles, [UserRole::BANNED->value]);
        } else {
            $roles[] = UserRole::BANNED->value;
        }

        $player->setRoles(array_values(array_diff($roles, [UserRole::USER->value])));

        $this->entityManager->flush();

        return $this->json($player, context: ['groups' => ['user:show:admin']]);
    }

    private function getPlayerFor(string $email): User
    {
        $player = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$player) {
            throw $this->createNotFoundException('User not found');
        }

        return $player;
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function userIndex(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:index:admin']);
    }

    #[Route(path: '/games', name: 'games_index', methods: ['GET'], format: 'json')]
    public function gameIndex(#[MapQueryParameter] ?string $fields): JsonResponse
    {
        $fields = $fields ? explode(',', $fields) : [];

        $games = $this->entityManager->getRepository(Game::class)->findByFields($fields);

        return $this->json(data: $games, context: [
            'groups' => 'game:index:admin',
            GameNormalizer::SHOW_SOLUTION => true,
            GameNormalizer::SHOW_HINT => true,
        ]);
    }
}
