<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Date\PreviousDateRequest;
use App\DTO\Game\DifficultiesRequest;
use App\DTO\Game\MoviePopularityCheckRequest;
use App\Entity\Achievement;
use App\Entity\Game;
use App\Enum\GameDifficulty;
use App\Enum\GameStatus;
use App\Enum\GameType;
use App\Enum\UserRole;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Serializer\GameNormalizer;
use App\Service\AuthenticatedUserService;
use App\Service\GameCreationService;
use App\Service\GameService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/me', name: 'me_')]
#[IsGranted(UserRole::USER->value)]
class MeController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly AuthenticatedUserService $authenticatedUserService,
        private readonly UserRepository           $userRepository,
        private readonly GameRepository           $gameRepository,
        private readonly GameService              $gameService,
        private readonly GameCreationService      $gameCreationService,
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

        return $this->json($data, context: [
            'groups' => ['user:me'],
            GameNormalizer::SHOW_SOLUTION => true,
        ]);
    }

    #[Route(path: '/games-stats', name: 'games_stats', methods: ['GET'])]
    public function gamesStats(
        #[MapQueryString] ?DifficultiesRequest $difficultiesRequest = null,
    ): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        $stats = $this->gameRepository->getPlayerStatsByMonthAndDifficulty(
            $player,
            $difficultiesRequest?->getDifficulties() ?? []
        );

        return $this->json($this->gameService->formatUserGamesData($stats));
    }

    /**
     * @throws \DateMalformedStringException
     */
    #[Route(path: '/games', name: 'games', methods: ['GET'])]
    public function gamesIndex(
        #[MapQueryString] PreviousDateRequest  $month,
        #[MapQueryString] ?DifficultiesRequest $difficultiesRequest = null,
    ): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        return $this->json(
            $this->gameRepository->getPlayerGamesByMonth(
                $player,
                $month->getMonth(),
                $difficultiesRequest?->getDifficulties() ?? []
            ),
            context: ['groups' => ['game:index']]
        );
    }

    #[Route(path: '/games', name: 'games_create', methods: ['POST'], format: 'json')]
    public function create(
        #[MapRequestPayload] MoviePopularityCheckRequest $moviePopularityCheckRequest
    ): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        // TODO: Appeler l'IA pour vérifier la popularité
        $popularityScore = 75;

        // TODO: Adopter un score minimal en fonction d'essais
        if ($popularityScore < 50) {
            return $this->json([
                'popularityScore' => $popularityScore
            ], 422);
        }

        $estimatedDate = $this->gameCreationService->getNextAvailableDate(GameType::MULTIVERS);

        $game = new Game()
            ->setSolution('Matrix des Anneaux')
            ->setEnglishSolution('The Matrix of the Rings')
            ->setHint('Neo doit détruire un anneau numérique')
            ->setDate($estimatedDate)
            ->setAuthor($player);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json([
            'game' => $game,
            'popularityScore' => $popularityScore,
            'prompt' => 'A dramatic sci-fi fantasy poster blending The Matrix and Lord of the Rings aesthetics'
        ], 201, context: ['groups' => ['game:show']]);
    }

    #[Route(path: '/games-generated', name: 'games_generated', methods: ['GET'], format: 'json')]
    public function generatedGamesIndex(): JsonResponse
    {
        $generatedGames = $this->gameRepository->findBy(['author' => $this->authenticatedUserService->getUserOrThrow()], ['date' => 'DESC']);

        return $this->json(data: $generatedGames, context: ['groups' => ['game:index']]);
    }
}
