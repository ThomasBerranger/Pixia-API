<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use DateTimeImmutable;
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

    #[Assert\NotNull]
    #[Assert\Type(\DateTimeInterface::class)]
    public DateTimeImmutable $date;

    public string $imageUrl = '';

    public function toGame(): Game
    {
        $game = new Game();

        $game->setSolution($this->solution);
        $game->setEnglishSolution($this->englishSolution);
        $game->setHint($this->hint);
        $game->setDate($this->date);
        $game->setImageUrl($this->imageUrl);

        return $game;
    }
}
