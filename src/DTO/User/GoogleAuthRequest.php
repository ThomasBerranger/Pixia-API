<?php

declare(strict_types=1);

namespace App\DTO\User;

use Symfony\Component\Validator\Constraints as Assert;

class GoogleAuthRequest
{

    /**
     * @var array{access_token: string}
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    public array $googleAccessToken;

    #[Assert\Regex(pattern: '/^[a-z0-9\-àâäéèêëïîôùûüÿæœç]+$/u')]
    public ?string $sponsor = null;

    public function getAccessToken(): string
    {
        return $this->googleAccessToken['access_token'];
    }
}
