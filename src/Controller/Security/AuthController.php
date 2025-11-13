<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Attribute\RateLimit;
use App\DTO\RegisterRequest;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    public function __construct(private readonly JWTTokenManagerInterface $JWTTokenManager)
    {
    }

    #[Route(path: '/register', name: 'register', methods: ['POST'])]
    #[RateLimit(limiterName: 'auth_form')]
    public function register(
        #[MapRequestPayload] RegisterRequest $registerRequest,
        UserPasswordHasherInterface $hasher,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $registerRequest->toUser($hasher);

        $em->persist($user);
        $em->flush();

        $token = $this->JWTTokenManager->create($user);

        return $this->json(['token' => $token], 201);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): Response
    {
        return $this->json(data: $this->getUser(), context: ['groups' => 'user:read']);
    }
}
