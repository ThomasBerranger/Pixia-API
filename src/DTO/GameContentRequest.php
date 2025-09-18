<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class GameContentRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Prompt is required')]
        #[Assert\Length(min: 5, max: 500)]
        public string $prompt
    ) {}
}
