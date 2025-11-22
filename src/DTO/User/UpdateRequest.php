<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà utilisé.', entityClass: User::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.', entityClass: User::class)]
class UpdateRequest
{
    #[Assert\Email(message: "Cet email n'est pas valide.")]
    public ?string $email = null;

    #[Assert\Length(min: 2, max: 255, minMessage: 'Ton pseudo doit faire au moins {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_\-\s\p{L}]+$/u', message: "Ce pseudo n'est pas valide.")]
    public ?string $username = null;

    public function update(User $user): void
    {
        if ($this->email && $this->email !== $user->getEmail()) {
            $user->setEmail($this->email);
        }

        if ($this->username && $this->username !== $user->getUsername()) {
            $user->setUsername($this->username);
        }
    }
}
