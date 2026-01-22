<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\MonthRequest;
use App\Entity\Achievement;
use App\Entity\Game;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserService;
use App\Service\GameService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/me', name: 'me_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly UserRepository           $userRepository,
        private readonly GameRepository           $gameRepository,
        private readonly GameService              $gameService,
    )
    {
    }

    /**
     * @throws Exception
     */
    #[Route(name: 'dashboard', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();
        $player->setRank($this->userRepository->getRank($player));

        $data['user'] = $player;
        $data['games'] = $this->entityManager->getRepository(Game::class)->countAvailableGames();

        $achievementData = $this->entityManager->getRepository(Achievement::class)->findLastWithCountFor($player, 3);
        $data['success'] = $achievementData['count'];
        $data['history'] = $achievementData['history'];

        $data = [
            ...$data,
            'streak' => [...$this->userRepository->getStreaks($player)],
            'playerCount' => $this->userRepository->count()
        ];

        return $this->json($data, context: ['groups' => ['user:me']]);
    }

    #[Route(path: '/games-stats', name: 'games_stats', methods: ['GET'])]
    public function gameStats(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();
        $stats = $this->gameRepository->getPlayerStatsByMonthAndDifficulty($player);

        return $this->json($this->gameService->formatUserGamesData($stats));
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route(path: '/games', name: 'games', methods: ['GET'])]
    public function gameIndex(#[MapQueryString] MonthRequest $month): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        return $this->json($this->gameRepository->getPlayerGamesByMonth($player, $month->getMonth()), context: ['groups' => ['game:index']]);
    }
}
