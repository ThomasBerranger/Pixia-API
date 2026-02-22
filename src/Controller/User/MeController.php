<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\DTO\Date\PreviousDateRequest;
use App\DTO\Game\DifficultiesRequest;
use App\DTO\Game\GameIdeaRequest;
use App\DTO\Game\GameUserUpdateRequest;
use App\DTO\Game\GameGenerateImageRequest;
use App\Entity\Achievement;
use App\Entity\Game;
use App\Enum\TokenCost;
use App\Enum\UserRole;
use App\Service\Game\GameStatusService;
use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Serializer\GameNormalizer;
use App\Service\API\OpenApiService;
use App\Service\AuthenticatedUserService;
use App\Service\ClaudeApiService;
use App\Service\Game\GameService;
use App\Service\ImageService;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

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
        private readonly ClaudeApiService         $claudeApiService,
        private readonly GameStatusService        $gameStatusService,
        private readonly OpenApiService           $openApiService,
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
            'authoredGameCount' => $this->gameRepository->count(['author' => $player]),
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
        #[MapRequestPayload] GameIdeaRequest $gameIdeaRequest
    ): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        $player->removeTokens(TokenCost::IDEA_ANALYSIS->value);

        try {
            $analysis = $this->claudeApiService->analyzeMovieIdea($gameIdeaRequest->idea, $player);

            if ($analysis['status'] === 'rejected') {
                $player->addTokens(TokenCost::REFUND->value);

                $this->entityManager->flush();

                return $this->json([
                    'message' => 'Les films proposés ne sont pas assez connus',
                    'score' => $analysis['score'],
                    'films' => $analysis['films'],
                    'tokens' => $player->getTokens(),
                ], Response::HTTP_OK);
            }

            $game = new Game()
                ->setSolution($analysis['solution'])
                ->setEnglishSolution($analysis['englishSolution'])
                ->setHint($analysis['hint'])
                ->setAuthor($player);

            $this->entityManager->persist($game);
            $this->entityManager->flush();

            return $this->json([
                'game' => $game,
                'score' => $analysis['score'],
                'films' => $analysis['films'],
                'image_prompt' => $analysis['image_prompt'],
                'tokens' => $player->getTokens(),
            ], Response::HTTP_CREATED, context: ['groups' => ['game:show']]);

        } catch (TransportExceptionInterface|\Exception $e) {
            return $this->json(['error' => 'Erreur lors de l\'analyse de l\'idée', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        Game                                       $game,
        #[MapRequestPayload] GameUserUpdateRequest $gameUserUpdateRequest
    ): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        try {
            $this->gameStatusService->handleGameSubmission($game, $gameUserUpdateRequest->status, $player);
            // TODO : handlegame approve et reject
        } catch (\LogicException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $gameUserUpdateRequest->updateGame($game);

        $this->entityManager->flush();

        return $this->json([
            'game' => $game,
            'tokens' => $player->getTokens()
        ], context: ['groups' => ['game:user:show']]);
    }

    #[Route(path: '/games/{id}/image', name: 'games_upload_image', methods: ['POST'])]
    #[IsGranted('edit', 'game')]
    public function gameUploadImage(Game $game, Request $request): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();

        $player->removeTokens(TokenCost::IMAGE_UPLOAD->value);

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

    #[Route(path: '/games/{id}/generate-image', name: 'games_generate_image', methods: ['POST'], format: 'json')]
    #[IsGranted('edit', 'game')]
    public function gameGenerateImage(
        Game                                          $game,
        #[MapRequestPayload] GameGenerateImageRequest $gameGenerateImageRequest,
    ): JsonResponse
    {
        set_time_limit(120);

        $player = $this->authenticatedUserService->getUserOrThrow();

        $player->removeTokens(TokenCost::IMAGE_GENERATION->value);

        try {
            $prompt = $gameGenerateImageRequest->resolvePrompt($game, $player, $this->claudeApiService);

            $imageUrl = $this->openApiService->requestGameImage($prompt);

            $filename = $this->imageService->downloadAndStore(
                $game->getType(),
                $game->getDate(),
                $imageUrl,
            );

            if ($filename === null) {
                $player->addTokens(TokenCost::REFUND->value);

                $this->entityManager->flush();

                return $this->json(['message' => 'Erreur lors du téléchargement de l\'image'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $game->setImage($filename);

            $this->entityManager->flush();

            return $this->json(['message' => 'Image générée avec succès']);
        } catch (\Throwable $e) {
            $player->addTokens(TokenCost::REFUND->value);

            $this->entityManager->flush();

            return $this->json(['message' => 'Erreur lors de la génération de l\'image', 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
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
        $player = $this->authenticatedUserService->getUserOrThrow();

        $generatedGames = $this->gameRepository->findBy(['author' => $player], ['date' => 'DESC']);

        return $this->json(data: ['games' => $generatedGames, 'tokens' => $player->getTokens()], context: ['groups' => ['game:index']]);
    }

    #[Route(path: '/tokens', name: 'tokens', methods: ['GET'], format: 'json')]
    public function tokens(): JsonResponse
    {
        return $this->json(data: $this->authenticatedUserService->getUserOrThrow()->getTokens());
    }

    #[Route(path: '/tokens/claim', name: 'claim_tokens', methods: ['POST'], format: 'json')]
    public function claimTokens(): JsonResponse
    {
        $player = $this->authenticatedUserService->getUserOrThrow();
        $addedTokens = 995;

        $player->addTokens($addedTokens);

        $this->entityManager->flush();

        return $this->json($addedTokens);
    }
}
