<?php

namespace App\DTO;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[UniqueEntity(fields: ['username'], message: 'Ce pseudo est déjà utilisé.', entityClass: User::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.', entityClass: User::class)]
class RegisterRequest
{
    #[Assert\NotBlank(message: 'Un email est requis.')]
    #[Assert\Email(message: "Cet email n'est pas valide.")]
    public ?string $email = null;

    #[Assert\NotBlank(message: 'Un mot de passe est requis.')]
    #[Assert\Length(min: 5, max: 255, minMessage: 'Ton mot de passe doit faire au moins {{ limit }} caractères.')]
//    #[Assert\PasswordStrength(
//        minScore: Assert\PasswordStrength::STRENGTH_WEAK,
//        message: 'Ton mot de passe est trop faible. Ajoute des majuscules, chiffres et symboles.'
//    )]
    public ?string $password = null;

    #[Assert\NotBlank(message: 'Un pseudo est requis.')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Ton pseudo doit faire au moins {{ limit }} caractères.')]
    #[Assert\Regex(pattern: '/^[a-zA-Z0-9_-]+$/', message: "Ce pseudo n'est pas valide.")]
    public ?string $username = null;

    public function toUser(UserPasswordHasherInterface $userPasswordHasher): User
    {
        $user = new User();

        $user
            ->setEmail($this->email)
            ->setPassword($userPasswordHasher->hashPassword($user, $this->password))
            ->setUsername($this->username);

        return $user;
    }
}
