<?php

declare(strict_types=1);

namespace App\DTO\User;

use App\Entity\User;
use App\Enum\UserRole;
use Symfony\Component\Validator\Constraints as Assert;

class SuperAdminUpdateRequest
{
    #[Assert\Email]
    public ?string $email = null;

    #[Assert\Length(min: 2, max: 255)]
    public ?string $username = null;

    public ?UserRole $role = null;

    #[Assert\GreaterThanOrEqual(0)]
    public ?int $tokens = null;

    #[Assert\GreaterThanOrEqual(0)]
    public ?int $score = null;

    public function updateUser(User $user): void
    {
        if ($this->email !== null) {
            $user->setEmail($this->email);
        }

        if ($this->username !== null) {
            $user->setUsername($this->username);
        }

        if ($this->role !== null) {
            $user->setRoles([$this->role->value]);
        }

        if ($this->tokens !== null) {
            $user->addTokens($this->tokens - $user->getTokens());
        }

        if ($this->score !== null) {
            $user->setScore($this->score);
        }
    }
}
