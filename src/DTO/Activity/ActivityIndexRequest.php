<?php

declare(strict_types=1);

namespace App\DTO\Activity;

use Symfony\Component\Validator\Constraints as Assert;

class ActivityIndexRequest
{
    public function __construct(
        #[Assert\Positive(message: 'La page doit être un entier positif')]
        public int $page = 1,

        #[Assert\Length(max: 255, maxMessage: 'La recherche ne peut excéder {{ limit }} caractères')]
        public ?string $search = null,
    )
    {
    }
}
