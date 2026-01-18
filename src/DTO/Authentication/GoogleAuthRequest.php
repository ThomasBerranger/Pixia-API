<?php

declare(strict_types=1);

namespace App\DTO\Authentication;

use Symfony\Component\Validator\Constraints as Assert;

class GoogleAuthRequest
{
    /**
     * @var array{access_token: string}
     */
    #[Assert\Collection(
        fields: [
            'access_token' => [
                new Assert\NotBlank(),
                new Assert\Type('string'),
            ],
        ],
        allowExtraFields: true,
        allowMissingFields: false
    )]
    public array $googleAccessToken;

    #[Assert\Length(min: 2, max: 255)]
    #[Assert\Regex(pattern: '/^[a-z0-9àâäéèêëïîôùûüÿæœç-]+$/u')]
    public ?string $sponsor = null;

    public function getAccessToken(): string
    {
        return $this->googleAccessToken['access_token'];
    }
}
