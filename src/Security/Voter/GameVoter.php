<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Game>
 */
final class GameVoter extends Voter
{
    public const string VIEW = 'view';
    public const string EDIT = 'edit';
    public const string SUBMIT = 'submit';
    public const string SUPERVISE = 'supervise';
    public const string APPROVE_OR_REJECT = 'approve_or_reject';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::SUBMIT, self::SUPERVISE, self::APPROVE_OR_REJECT], true) && $subject instanceof Game;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
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
            self::SUBMIT => $this->canSubmit($currentUser, $targetGame),
            self::SUPERVISE => $this->canSupervise($currentUser, $targetGame),
            self::APPROVE_OR_REJECT => $this->canApproveOrReject($currentUser, $targetGame),
            default => false,
        };
    }

    private function canView(User $currentUser, Game $targetGame): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        $status = $targetGame->getStatus();

        if ($status === GameStatus::DRAFT) {
            return $targetGame->isAuthoredBy($currentUser);
        }

        return $targetGame->isAuthoredBy($currentUser) || $targetGame->isSupervisedBy($currentUser);
    }

    private function canEdit(User $currentUser, Game $targetGame): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        $status = $targetGame->getStatus();

        if ($status === GameStatus::DRAFT) {
            return $targetGame->isAuthoredBy($currentUser);
        }

        if ($status === GameStatus::PENDING) {
            return $currentUser->isAdmin();
        }

        return false;
    }

    private function canSubmit(User $currentUser, Game $targetGame): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        return $targetGame->getStatus() === GameStatus::DRAFT && $targetGame->isAuthoredBy($currentUser);
    }

    private function canSupervise(User $currentUser, Game $targetGame): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        return $currentUser->isAdmin()
            && $targetGame->getStatus() === GameStatus::PENDING
            && $targetGame->getSupervisor() === null;
    }

    private function canApproveOrReject(User $currentUser, Game $targetGame): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        return $targetGame->getStatus() === GameStatus::PENDING && $targetGame->isSupervisedBy($currentUser);
    }
}
