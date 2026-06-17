<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use Symfony\Component\Validator\Constraints as Assert;

class SuperAdminGameCreateRequest
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $solution;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $englishSolution;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $hint;

    #[Assert\NotBlank(message: 'Un type est requis.')]
    public GameType $type;

    public ?GameDifficulty $difficulty = null;

    public function toGame(): Game
    {
        $game = (new Game())
            ->setSolution($this->solution)
            ->setEnglishSolution($this->englishSolution)
            ->setHint($this->hint)
            ->setType($this->type);

        if ($this->difficulty !== null) {
            $game->setDifficulty($this->difficulty);
        }

        return $game;
    }
}
