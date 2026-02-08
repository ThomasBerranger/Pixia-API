<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Game>
 */
final class GameVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true) && $subject instanceof Game;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $currentUser = $token->getUser();

        if (!$currentUser instanceof User) {
            return false;
        }

        /** @var Game $targetGame */
        $targetGame = $subject;

        return match ($attribute) {
            self::VIEW => $this->canView($currentUser, $targetGame),
            self::EDIT => $this->canEdit($currentUser, $targetGame),
            default => false,
        };
    }

    private function canView(User $currentUser, Game $targetGame): bool
    {
        return $currentUser->isSuperAdmin()
            || $currentUser === $targetGame->getAuthor()
            || $currentUser === $targetGame->getSupervisor();
    }

    private function canEdit(User $currentUser, Game $targetGame): bool
    {
        if ($targetGame->getApprovedAt()) {
            return $currentUser->isSuperAdmin();
        }

        return $this->canView($currentUser, $targetGame);
    }
}
