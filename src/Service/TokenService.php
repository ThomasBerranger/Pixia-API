<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Enum\ActivityType;
use App\Enum\TokenCost;
use App\Repository\ActivityRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

readonly class TokenService
{
    private const int ADMIN_CLAIM_AMOUNT = 995;
    private const int CLAIM_COOLDOWN_DAYS = 5;

    /** @var list<array{probability: int, max: int}> */
    private const array CLAIM_TIERS = [
        ['probability' => 50, 'max' => 40],
        ['probability' => 30, 'max' => 80],
        ['probability' => 15, 'max' => 120],
        ['probability' => 5, 'max' => 180],
    ];

    public function __construct(
        private ActivityRepository     $activityRepository,
        private ActivityService        $activityService,
        private EntityManagerInterface $entityManager,
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

    public function canClaimTokens(User $player): DateTimeImmutable|true
    {
        $lastActivity = $this->activityRepository->findLastByPlayerAndType($player, ActivityType::PLAYER_TOKENS_CLAIM);

        if ($lastActivity === null) {
            return true;
        }

        $nextClaim = $lastActivity->getCreatedAt()->modify(sprintf('+%d days', self::CLAIM_COOLDOWN_DAYS));

        if ($nextClaim <= new DateTimeImmutable()) {
            return true;
        }

        return $nextClaim;
    }

    public function canClaimAdminTokens(User $player): bool
    {
        return $player->isAdmin() && !$this->activityRepository->hasClaimedThisMonth($player, ActivityType::ADMIN_TOKENS_CLAIM);
    }

    public function claimTokens(User $player): int
    {
        if ($this->canClaimTokens($player) !== true) {
            throw new UnprocessableEntityHttpException('Tokens pas encore disponibles');
        }

        $amount = $this->calculateTokenReward();

        $player->addTokens($amount);

        $this->activityService->recordPlayerTokensClaim($player, $amount);

        $this->entityManager->flush();

        return $amount;
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

    private function calculateTokenReward(): int
    {
        $roll = random_int(1, 100);
        $cumulative = 0;
        $max = self::CLAIM_TIERS[0]['max'];

        foreach (self::CLAIM_TIERS as $tier) {
            $cumulative += $tier['probability'];

            if ($roll <= $cumulative) {
                $max = $tier['max'];
                break;
            }
        }

        return (int) (round(random_int(5, $max) / 5) * 5);
    }
}
