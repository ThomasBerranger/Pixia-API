<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\GameCreationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends BaseController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GameCreationService    $gameCreationService,
    )
    {
    }

    #[Route(path: '/users', name: 'users_index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        return $this->json(data: $users, context: ['groups' => 'user:index:admin']);
    }

    #[Route('/game-solution-exist', name: 'game-solution-exist', methods: ['GET'], format: 'json')]
    public function gameSolutionExist(#[MapQueryParameter] string $solution): JsonResponse
    {
        $solutionFound = $this->gameCreationService->gameSolutionExist($solution);

        return $this->json(data: $solutionFound);
    }
}
