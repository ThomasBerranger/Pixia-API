<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

readonly class AuthenticatedUserService
{
    public function __construct(private Security $security)
    {
    }

    public function getUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }

    public function getUserOrThrow(): User
    {
        $user = $this->getUser();

        if (!$user) {
            throw new \LogicException('User must be authenticated');
        }

        return $user;
    }
}
