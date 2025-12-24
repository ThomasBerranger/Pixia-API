<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Enum\GameDifficulty;
use DateTimeImmutable;
use Symfony\Component\Validator\Constraints as Assert;

class GameUpdateRequest
{
    //    #[Assert\NotNull]
    //    public GameType $type = GameType::MULTIVERS;

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

    #[Assert\NotNull]
    public GameDifficulty $difficulty;
}
