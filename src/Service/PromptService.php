<?php

namespace App\Service;

use App\Enum\GameType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class PromptService
{
    public function __construct(
        #[Autowire('%env(MULTIVERS_PROMPT_INSTRUCTION)%')] private string $promptInstruction
    )
    {
    }

    public function formatPromptRequest(
        string   $prompt,
        GameType $gameType = GameType::MULTIVERS): string
    {
        $finalPrompt = $prompt;

        if (GameType::MULTIVERS === $gameType) {
            $finalPrompt = $this->promptInstruction . ' La demande de création de prompt est la suivante : ' . $finalPrompt;
        }

        return $finalPrompt;
    }
}
