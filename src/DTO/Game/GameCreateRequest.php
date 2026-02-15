<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use Symfony\Component\Validator\Constraints as Assert;

class GameCreateRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    public string $solution;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    public string $englishSolution;

    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    public string $hint;

    public string $imageUrl = '';

    public function toGame(): Game
    {
        $game = new Game();

        $game->setSolution($this->solution);
        $game->setEnglishSolution($this->englishSolution);
        $game->setHint($this->hint);
        $game->setImageUrl($this->imageUrl);

        return $game;
    }
}
