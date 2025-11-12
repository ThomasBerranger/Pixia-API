<?php

namespace App\Controller;

use App\Service\GameCreationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
final class AdminController extends BaseController
{
    public function __construct(
        private readonly GameCreationService $gameCreationService,
    ) {
    }

    #[Route('/game-solution-exist', name: 'game-solution-exist', methods: ['GET'], format: 'json')]
    public function gameSolutionExist(#[MapQueryParameter] string $solution): JsonResponse
    {
        $solutionFound = $this->gameCreationService->gameSolutionExist($solution);

        return $this->json(data: $solutionFound);
    }
}
