<?php

declare(strict_types=1);

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\GameType;
use App\Repository\GameRepository;
use DateTimeImmutable;

readonly class GameDateService
{
    public function __construct(
        private GameRepository $gameRepository,
    ) {
    }

    private function isDateAvailable(DateTimeImmutable $date, GameType $type): bool
    {
        return $this->gameRepository->findOneBy([
            'date' => $date,
            'type' => $type,
            'status' => GameStatus::APPROVED
        ]) === null;
    }

    public function validateDateChange(DateTimeImmutable $newDate, Game $game, ?User $user): void
    {
        if (!$user instanceof User) {
            throw new \LogicException('Utilisateur non authentifié');
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        if (!$game->isSupervisedBy($user)) {
            throw new \LogicException('Seul le superviseur du Pixia peut modifier la date');
        }

        if ($newDate < new DateTimeImmutable('+1 week')->setTime(0, 0)) {
            throw new \LogicException('La date doit être au minimum dans une semaine');
        }

        if (!$this->isDateAvailable($newDate, $game->getType())) {
            throw new \LogicException('Cette date est déjà prise pour ce type de Pixia');
        }
    }
}
