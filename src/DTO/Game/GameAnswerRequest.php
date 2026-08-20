<?php

declare(strict_types=1);

namespace App\DTO\Game;

use Symfony\Component\Validator\Constraints as Assert;

class GameAnswerRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Une réponse est obligatoire.')]
        #[Assert\Length(
            min: 2,
            max: 255,
            minMessage: 'La réponse doit contenir au moins {{ limit }} caractères',
            maxMessage: 'La réponse ne peut pas dépasser {{ limit }} caractères'
        )]
        public string $answer,
    )
    {
    }
}
