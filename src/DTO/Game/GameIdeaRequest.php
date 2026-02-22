<?php

declare(strict_types=1);

namespace App\DTO\Game;

use Symfony\Component\Validator\Constraints as Assert;

class GameIdeaRequest
{
    #[Assert\NotBlank(message: 'L\'idée ne peut pas être vide')]
    #[Assert\Length(
        min: 10,
        max: 500,
        minMessage: 'L\'idée doit faire au moins {{ limit }} caractères',
        maxMessage: 'L\'idée ne peut pas dépasser {{ limit }} caractères'
    )]
    public string $idea;
}
