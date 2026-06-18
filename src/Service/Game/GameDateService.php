<?php

declare(strict_types=1);

namespace App\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use DateTimeImmutable;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class GameDateService
{
    public function __construct(
        private GameRepository $gameRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Invariant : au plus 1 Game APPROVED par date, tous types confondus.
     * À appeler dès qu'un game devient (ou reste) APPROVED.
     */
    public function assertUniqueApprovedDate(Game $game): void
    {
        if ($this->gameRepository->findApprovedByDate($game->getDate(), $game->getId()) !== null) {
            throw new UnprocessableEntityHttpException('Une partie est déjà publiée à cette date');
        }
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
            throw new AccessDeniedHttpException('Seul le superviseur du Pixia peut modifier la date');
        }

        if ($newDate < $this->clock->now()->modify('+1 week')->setTime(0, 0)) {
            throw new UnprocessableEntityHttpException('La date doit être au minimum dans une semaine');
        }
    }
}
