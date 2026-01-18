<?php

declare(strict_types=1);

namespace App\DTO\Authentication;

use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordRequest
{
    #[Assert\NotBlank(message: 'Un token est requis.')]
    public string $token;

    #[Assert\NotBlank(message: 'Un mot de passe est requis.')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Ton mot de passe doit faire au moins {{ limit }} caractères.')]
    public string $password;
}
