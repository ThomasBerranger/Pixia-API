<?php

declare(strict_types=1);

namespace App\DTO\Authentication;

use Symfony\Component\Validator\Constraints as Assert;

class ForgotPasswordRequest
{
    #[Assert\NotBlank(message: 'Un email est requis.')]
    #[Assert\Email(message: "Cet email n'est pas valide.")]
    #[Assert\Length(max: 255)]
    public string $email;
}
