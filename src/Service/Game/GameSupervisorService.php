<?php

declare(strict_types=1);

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;

readonly class GameSupervisorService
{
    /**
     * Assigne un utilisateur comme supervisor du Pixia.
     *
     * @throws \LogicException si le Pixia n'est pas en attente ou a déjà un supervisor
     */
    public function assignSupervisor(Game $game, User $user): void
    {
        if ($game->getStatus() !== GameStatus::PENDING) {
            throw new \LogicException('Seuls les Pixias en attente de validation peuvent être pris en charge');
        }

        if ($game->getSupervisor() !== null) {
            throw new \LogicException('Ce Pixia est déjà pris en charge par un modérateur');
        }

        $game->setSupervisor($user);
    }
}
