<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, User>
 */
final class UserVoter extends Voter
{
    public const string VIEW = 'view';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var User $targetUser */
        $targetUser = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($currentUser, $targetUser),
            default => false,
        };
    }

    private function canView(User $currentUser, User $targetUser): bool
    {
        if ($currentUser->isSuperAdmin() || $currentUser->isAdmin()) {
            return true;
        }

        return $currentUser->getUserIdentifier() === $targetUser->getUserIdentifier();
    }
}
