<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class OpenAiRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Prompt is required')]
        #[Assert\Length(min: 5, max: 2000)]
        public string $prompt,
    ) {
    }
}
