<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\GameDifficulty;
use App\Enum\GameType;
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
    public string $hint;

    #[Assert\NotNull]
    #[Assert\Type(\DateTimeInterface::class)]
    public \DateTime $date;

    #[Assert\NotNull(message: 'Difficulty is required')]
    public GameDifficulty $difficulty = GameDifficulty::EASY;

    //    #[Assert\Length(min: 5, max: 255)]
    //    public ?string $image = null;
}
