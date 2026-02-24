<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Service\API\ClaudeApiService;
use Symfony\Component\Validator\Constraints as Assert;

class GameGenerateImageRequest
{
    public function __construct(
        #[Assert\Length(min: 5, max: 2000)]
        public ?string $prompt = null,
        #[Assert\Length(min: 5, max: 500)]
        public ?string $solution = null,
    ) {
    }

    public function resolvePrompt(Game $game, User $player, ClaudeApiService $claudeApiService): string
    {
        if ($this->prompt !== null) {
            return $this->prompt;
        }

        $solution = $this->solution ?? $game->getSolution();
        $analysis = $claudeApiService->analyzeMovieIdea($solution, $player);

        return $analysis['image_prompt']
            ?? throw new \RuntimeException('Impossible de générer un prompt image pour cette combinaison de films.');
    }
}
