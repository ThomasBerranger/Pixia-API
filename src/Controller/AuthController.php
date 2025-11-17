<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\DTO\RegisterRequest;
use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\Badge as BadgeEnum;
use App\Service\AuthenticatedUserService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $JWTTokenManager,
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService
    )
    {
    }

    #[Route(path: '/register', name: 'register', methods: ['POST'])]
    #[RateLimit(limiterName: 'auth_form')]
    public function register(
        #[MapRequestPayload] RegisterRequest $registerRequest,
        UserPasswordHasherInterface          $hasher,
        EntityManagerInterface               $em,
    ): JsonResponse
    {
        $user = $registerRequest->toUser($hasher);

        $betaTesterBadge = new Badge();
        $betaTesterBadge->setName(BadgeEnum::BETA_TESTER)->setPlayer($user);

        $em->persist($betaTesterBadge);
        $em->persist($user);

        $em->flush();

        $token = $this->JWTTokenManager->create($user);

        return $this->json(['token' => $token], 201);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(): JsonResponse
    {
        $user = $this->authenticatedUserService->getUserOrThrow();

        $data['user'] = $user;
        $data['rank'] = $this->entityManager->getRepository(User::class)->getRank($user);
        $data['games'] = $this->entityManager->getRepository(Game::class)->count();
        $data['success'] = $this->entityManager->getRepository(Achievement::class)->count(['player' => $user]);
        $data['history'] = $this->entityManager->getRepository(Achievement::class)->findLastFor($user, 3);

        return $this->json($data, context: ['groups' => ['user:show', 'achievement:index']]);
    }
}
