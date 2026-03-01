<?php

declare(strict_types=1);

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Service\ActivityService;
use App\Service\API\ClaudeApiService;
use App\Service\API\OpenApiService;
use App\Service\ImageService;

readonly class GameImageGenerationService
{
    public function __construct(
        private ClaudeApiService $claudeApiService,
        private OpenApiService   $openApiService,
        private ImageService     $imageService,
        private ActivityService  $activityService,
    ) {
    }

    public function generate(User $player, Game $game, ?string $prompt, ?string $solution): void
    {
        try {
            $resolvedPrompt = $this->resolvePrompt($player, $game, $prompt, $solution);

            $this->activityService->recordImageGenerationRequest($player, $game, $resolvedPrompt);

            $imageUrl = $this->openApiService->requestGameImage($resolvedPrompt);

            $this->activityService->recordImageGeneration($player, $game, $resolvedPrompt, $imageUrl);

            $filename = $this->imageService->downloadAndStore(
                $game->getType(),
                $game->getDate(),
                $imageUrl,
                $game->getImage(),
            );

            if ($filename === null) {
                throw new \RuntimeException('Erreur lors du téléchargement de l\'image');
            }

            $game->setImage($filename);
        } catch (\Throwable $e) {
            $this->activityService->recordImageGenerationError($player, $game, $e);

            throw $e;
        }
    }

    private function resolvePrompt(User $player, Game $game, ?string $prompt, ?string $solution): string
    {
        if ($prompt !== null) {
            return $prompt;
        }

        $idea = $solution ?? $game->getSolution();
        $analysis = $this->claudeApiService->analyzeMovieIdea($idea, $player);

        return $analysis->getImagePrompt();
    }
}
