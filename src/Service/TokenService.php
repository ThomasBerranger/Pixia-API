<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\TokenCost;
use App\Repository\ActivityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class TokenService
{
    private const int ADMIN_CLAIM_AMOUNT = 995;

    public function __construct(
        private ActivityRepository     $activityRepository,
        private ActivityService        $activityService,
        private EntityManagerInterface $entityManager
    )
    {
    }

    public function removeTokens(User $user, TokenCost $cost): void
    {
        try {
            $user->removeTokens($cost->value);
        } catch (\LogicException) {
            throw new UnprocessableEntityHttpException('Tokens insuffisants');
        }
    }

    public function canClaimAdminTokens(User $player): bool
    {
        return !$this->activityRepository->hasClaimedTokensThisMonth($player, ActivityType::ADMIN_TOKENS_CLAIM);
    }

    public function claimAdminTokens(User $player): int
    {
        if (!$this->canClaimAdminTokens($player)) {
            throw new UnprocessableEntityHttpException('Tokens déjà réclamés ce mois-ci');
        }

        $player->addTokens(self::ADMIN_CLAIM_AMOUNT);

        $this->activityService->recordAdminTokensClaim($player, self::ADMIN_CLAIM_AMOUNT);

        $this->entityManager->flush();

        return self::ADMIN_CLAIM_AMOUNT;
    }
}
