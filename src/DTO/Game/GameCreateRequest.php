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
        return new Game()
            ->setSolution($this->solution)
            ->setEnglishSolution($this->englishSolution)
            ->setHint($this->hint)
            ->setImageUrl($this->imageUrl);
    }
}
