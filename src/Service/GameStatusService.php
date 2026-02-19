<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;

readonly class GameStatusService
{
    /**
     * @throws \LogicException
     */
    public function validateStatusTransition(Game $game, GameStatus $from, GameStatus $to, ?User $user): void
    {
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur non authentifié');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $isAllowed = match ([$from, $to]) {
            [GameStatus::DRAFT, GameStatus::PENDING] => $this->canSubmitForReview($game, $user),
            [GameStatus::PENDING, GameStatus::APPROVED] => $this->canApprove($game, $user),
            [GameStatus::PENDING, GameStatus::REJECTED] => $this->canReject($game, $user),
            default => false,
        };

        if (!$isAllowed) {
            throw new \LogicException(sprintf(
                'Transition de statut non autorisée: %s → %s',
                $from->value,
                $to->value
            ));
        }
    }

    private function canSubmitForReview(Game $game, User $user): bool
    {
        return $game->isAuthoredBy($user);
    }

    private function canApprove(Game $game, User $user): bool
    {
        return $game->isSupervisedBy($user);
    }

    private function canReject(Game $game, User $user): bool
    {
        return $game->isSupervisedBy($user);
    }
}
