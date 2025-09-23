<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserVoter extends Voter
{
    public const VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof UserInterface) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($currentUser, $targetUser),
            default => false,
        };
    }

    private function canView(UserInterface $currentUser, User $targetUser): bool
    {
        if (in_array('ROLE_ADMIN', $currentUser->getRoles())) {
            return true;
        }

        return $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier();
    }
}
