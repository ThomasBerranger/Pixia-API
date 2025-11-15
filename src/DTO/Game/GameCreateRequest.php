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
    public string $hint;

    #[Assert\NotNull]
    #[Assert\Type(\DateTimeInterface::class)]
    public \DateTime $date;

    public string $imageUrl = '';

    public function toGame(): Game
    {
        $game = new Game();

        $game->setSolution($this->solution);
        $game->setHint($this->hint);
        $game->setDate($this->date);
        $game->setImageUrl($this->imageUrl);

        return $game;
    }
}
