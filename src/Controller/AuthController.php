<?php

declare(strict_types=1);

namespace App\Controller;

use App\Attribute\RateLimit;
use App\DTO\User\GoogleAuthRequest;
use App\DTO\User\RegisterRequest;
use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Service\AuthenticatedUserService;
use App\Service\GoogleApiService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface    $JWTTokenManager,
        private readonly EntityManagerInterface      $entityManager,
        private readonly AuthenticatedUserService    $authenticatedUserService,
        private readonly UserService                 $userService,
        private readonly GoogleApiService            $googleApiService,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    )
    {
    }

    #[Route(path: '/register', name: 'register', methods: ['POST'])]
    #[RateLimit(limiterName: 'auth_form')]
    public function create(
        #[MapRequestPayload] RegisterRequest $registerRequest,
    ): JsonResponse
    {
        $user = $registerRequest->toUser($this->userPasswordHasher);

        $this->register($user, $registerRequest->sponsor);

        $this->entityManager->flush();

        $token = $this->JWTTokenManager->create($user);

        return $this->json(['token' => $token], 201);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(): JsonResponse
    {
        $user = $this->authenticatedUserService->getUserOrThrow();

        $data['user'] = $user;
        $data['rank'] = $this->entityManager->getRepository(User::class)->getRank($user);

        $data['games'] = $this->entityManager->getRepository(Game::class)->countAvailableGames();

        $achievementData = $this->entityManager->getRepository(Achievement::class)->findLastWithCountFor($user, 3);
        $data['success'] = $achievementData['count'];
        $data['history'] = $achievementData['history'];

        return $this->json($data, context: ['groups' => ['user:me', 'achievement:index']]);
    }

    /**
     * @throws RandomException
     */
    #[Route(path: '/google-auth', name: 'google_auth', methods: ['POST'])]
    //    #[RateLimit(limiterName: 'auth_form')]
    public function googleAuth(
        #[MapRequestPayload] GoogleAuthRequest $googleAuthRequest,
    ): JsonResponse
    {
        try {
            $googleUserInfo = $this->googleApiService->authenticate($googleAuthRequest->getAccessToken());
        } catch (ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {
            return $this->json(['Invalid token' => $e->getMessage()], 500);
        }

        $email = $googleUserInfo['email'];
        $username = $googleUserInfo['name'];

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($user) {
            $user->setPassword($this->userPasswordHasher->hashPassword($user, bin2hex(random_bytes(10))));
        } else {
            $user = new User();

            $usernameAvailable = false;
            $i = 0;
            while (!$usernameAvailable) {
                $tentativeUsername = $i === 0 ? $username : $username . '_' . $i;

                if ($this->entityManager->getRepository(User::class)->findOneBy(['username' => $tentativeUsername])) {
                    $i++;
                } else {
                    $usernameAvailable = true;
                    $user->setUsername($tentativeUsername);
                }
            }

            $user->setEmail($email)
                ->setPassword($this->userPasswordHasher->hashPassword($user, bin2hex(random_bytes(10))));

            $this->register($user, $googleAuthRequest->sponsor);
        }

        $this->entityManager->flush();

        $token = $this->JWTTokenManager->create($user);

        return $this->json(['token' => $token]);
    }

    private function register(User $user, ?string $sponsor): void
    {
        if ($sponsor) {
            $sponsor = $this->userService->findAndRewardSponsor($sponsor);

            $user->setSponsor($sponsor);
        }

        $user->addBadge(new Badge(BadgeName::BETA_TESTER));

        $this->entityManager->persist($user);
    }
}
