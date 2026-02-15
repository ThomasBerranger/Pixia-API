<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Entity\Game;
use App\Enum\GameDifficulty;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class GameAdminUpdateRequest
{
    //    #[Assert\NotNull]
    //    public GameType $type = GameType::MULTIVERS;

    #[Assert\Length(min: 5, max: 255)]
    public ?string $solution = null;

    #[Assert\Length(min: 5, max: 255)]
    public ?string $englishSolution = null;

    #[Assert\Length(min: 5, max: 255)]
    public ?string $hint = null;

    #[Assert\Type(\DateTimeInterface::class)]
    public ?DateTimeImmutable $date = null;

    public ?GameDifficulty $difficulty = null;

    public ?bool $assignToMe = null;

    public function updateGame(Game $game): void
    {
        if ($this->solution !== null) {
            $game->setSolution($this->solution);
        }

        if ($this->englishSolution !== null) {
            $game->setEnglishSolution($this->englishSolution);
        }

        if ($this->hint !== null) {
            $game->setHint($this->hint);
        }

        if ($this->date !== null) {
            $game->setDate($this->date);
        }

        if ($this->difficulty !== null) {
            $game->setDifficulty($this->difficulty);
        }
    }
}
