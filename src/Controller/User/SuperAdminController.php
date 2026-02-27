<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Contribution\ContributionUpdateRequest;
use App\DTO\Game\SuperAdminGameCreateRequest;
use App\DTO\User\SuperAdminUpdateRequest;
use App\Entity\Contribution;
use App\Entity\User;
use App\Enum\ContributionStatus;
use App\Enum\UserRole;
use App\Repository\GameRepository;
use App\Service\ContributionService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super-admin', name: 'super_admin_')]
#[IsGranted(UserRole::SUPER_ADMIN->value)]
final class SuperAdminController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ContributionService      $contributionService,
        private readonly GameRepository           $gameRepository,
    )
    {
    }

    #[Route('/impersonate/{id}', name: 'impersonate', methods: ['POST'])]
    public function impersonate(User $player): JsonResponse
    {
        $jwtToken = $this->jwtManager->create($player);

        return $this->json(['token' => $jwtToken]);
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function userIndex(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        array_shift($users);

        return $this->json(data: $users, context: ['groups' => 'user:index:super_admin']);
    }

    #[Route('/users/{id}', name: 'users_update', methods: ['PATCH'], format: 'json')]
    public function userUpdate(
        User $player,
        #[MapRequestPayload] SuperAdminUpdateRequest $superAdminUpdateRequest,
    ): JsonResponse
    {
        $superAdminUpdateRequest->updateUser($player);

        $this->entityManager->flush();

        return $this->json(data: $player, context: ['groups' => 'user:index:super_admin']);
    }

    #[Route(path: '/games', name: 'games_index', methods: ['GET'], format: 'json')]
    public function gameIndex(#[MapQueryParameter] ?string $fields): JsonResponse
    {
        $fields = $fields ? explode(',', $fields) : [];

        $games = $this->gameRepository->findByFields($fields);

        return $this->json(data: $games, context: ['groups' => 'game:index:admin']);
    }

    #[Route(path: '/games', name: 'games_create', methods: ['POST'], format: 'json')]
    public function gameCreate(
        #[MapRequestPayload] SuperAdminGameCreateRequest $superAdminGameCreateRequest,
    ): JsonResponse
    {
        $game = $superAdminGameCreateRequest->toGame();

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json(data: $game, status: Response::HTTP_CREATED, context: [
            'groups' => ['game:show:admin'],
        ]);
    }

    #[Route(path: '/contributions', name: 'contributions_index', methods: ['GET'], format: 'json')]
    public function contributionIndex(): JsonResponse
    {
        return $this->json(data: $this->entityManager->getRepository(Contribution::class)->findAllWithAuthor());
    }

    #[Route(path: '/contributions/{id}', name: 'contributions_update', methods: ['PATCH'], format: 'json')]
    public function contributionUpdate(Contribution $contribution, #[MapRequestPayload] ContributionUpdateRequest $contributionUpdateRequest): JsonResponse
    {
        $contribution->setStatus($contributionUpdateRequest->status);

        if ($contributionUpdateRequest->status === ContributionStatus::APPROVED) {
            $this->contributionService->rewardPlayer($contribution->getAuthor(), $contribution->getType());
        }

        $this->entityManager->flush();

        return $this->json(data: $contribution, context: ['groups' => ['contribution:show']]);
    }
}
