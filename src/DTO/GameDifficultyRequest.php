<?php

namespace App\DTO;

use App\Enum\GameDifficulty;
use Symfony\Component\Validator\Constraints as Assert;

class GameDifficultyRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Difficulty is required')]
        #[Assert\Type(type: GameDifficulty::class, message: 'Invalid difficulty value')]
        public GameDifficulty $difficulty,
    ) {
    }
}
