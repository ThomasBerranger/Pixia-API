<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class GameAnswerRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Answer is required')]
        #[Assert\Length(min: 5, max: 255)]
        public string $answer,
    ) {
    }
}
