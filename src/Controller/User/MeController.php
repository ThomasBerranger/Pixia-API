<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Date\PreviousDateRequest;
use App\DTO\Game\DifficultiesRequest;
use App\DTO\Game\GameUserUpdateRequest;
use App\DTO\Game\MoviePopularityCheckRequest;
use App\Entity\Achievement;
use App\Entity\Game;
use App\Enum\UserRole;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Serializer\GameNormalizer;
use App\Service\AuthenticatedUserService;
use App\Service\GameService;
use App\Service\ImageService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
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
        private readonly ImageService             $imageService,
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
            'playerCount' => $this->userRepository->count(),
            'authoredGameCount' =>$this->gameRepository->count(['author' => $player]),
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
    public function gameCreate(
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

        // TODO: Appeler l'IA pour la génération du Pixia
        $game = new Game()
            ->setSolution('Matrix des Anneaux')
            ->setEnglishSolution('The Matrix of the Rings')
            ->setHint('Neo doit détruire un anneau numérique')
            ->setAuthor($player);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return $this->json([
            'game' => $game,
            'popularityScore' => $popularityScore,
            'prompt' => 'A dramatic sci-fi fantasy poster blending The Matrix and Lord of the Rings aesthetics'
        ], 201, context: ['groups' => ['game:show']]);
    }

    #[Route(path: '/games/{id}', name: 'games_show', methods: ['GET'], format: 'json')]
    #[IsGranted('view', 'game')]
    public function gameShow(Game $game): JsonResponse
    {
        return $this->json($game, context: ['groups' => ['game:user:show']]);
    }

    #[Route(path: '/games/{id}', name: 'games_update', methods: ['PATCH'], format: 'json')]
    #[IsGranted('edit', 'game')]
    public function gameUpdate(
        Game $game,
        #[MapRequestPayload] GameUserUpdateRequest $gameUserUpdateRequest
    ): JsonResponse
    {
        $gameUserUpdateRequest->updateGame($game);

        $this->entityManager->flush();

        return $this->json($game, context: ['groups' => ['game:user:show']]);
    }

    #[Route(path: '/games/{id}/image', name: 'games_upload_image', methods: ['POST'])]
    #[IsGranted('edit', 'game')]
    public function gameUploadImage(Game $game, Request $request): JsonResponse
    {
        $uploadedFile = $request->files->get('image');

        if ($uploadedFile === null) {
            throw new UnprocessableEntityHttpException('Aucun fichier image fourni.');
        }

        $filename = $this->imageService->store(
            $game->getType(),
            $game->getDate(),
            $game->getImage(),
            $uploadedFile
        );

        $game->setImage($filename);
        $this->entityManager->flush();

        return $this->json(['message' => 'Image ajoutée avec succès']);
    }

    #[Route(path: '/games/{id}/image', name: 'games_delete_image', methods: ['DELETE'])]
    #[IsGranted('edit', 'game')]
    public function gameDeleteImage(Game $game): JsonResponse
    {
        $imageName = $game->getImage();

        if ($imageName === null) {
            return $this->json(['message' => 'Aucune image à supprimer'], 404);
        }

        $this->imageService->delete(
            $game->getType(),
            $game->getDate(),
            $imageName
        );

        $game->setImage(null);
        $this->entityManager->flush();

        return $this->json(['message' => 'Image supprimée avec succès']);
    }

    #[Route(path: '/games-generated', name: 'games_generated', methods: ['GET'], format: 'json')]
    public function generatedGamesIndex(): JsonResponse
    {
        $generatedGames = $this->gameRepository->findBy(['author' => $this->authenticatedUserService->getUserOrThrow()], ['date' => 'DESC']);

        return $this->json(data: $generatedGames, context: ['groups' => ['game:index']]);
    }
}
