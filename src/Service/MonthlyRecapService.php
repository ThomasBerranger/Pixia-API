<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\MonthlyRecap;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\RecapTier;
use App\Enum\TokenCost;
use App\Repository\AchievementRepository;
use App\Repository\BadgeRepository;
use App\Repository\GameRepository;
use App\Repository\MonthlyLeaderboardRepository;
use DateTimeImmutable;

final readonly class MonthlyRecapService
{
    public function __construct(
        private AchievementRepository        $achievementRepository,
        private MonthlyLeaderboardRepository $monthlyLeaderboardRepository,
        private BadgeRepository              $badgeRepository,
        private GameRepository               $gameRepository,
    )
    {
    }

    public function getRecapFor(User $player, DateTimeImmutable $month, ?string $championUsername, int $championScore): MonthlyRecap
    {
        $firstDay = $month->modify('first day of this month');

        $points = $this->monthlyLeaderboardRepository
            ->findOneBy(['player' => $player, 'month' => $firstDay])
            ?->getScore() ?? 0;

        $createdPixias = array_map(
            static fn (array $row): array => [
                'label' => $row['game']->getSolution(),
                'guessers' => $row['winners'],
                'attempts' => $row['game']->getAttempts(),
            ],
            $this->gameRepository->findAuthoredInMonthWithWinners($player, $month),
        );

        $tokens = $player->getTokens();
        $rank = $this->monthlyLeaderboardRepository->getMonthlyRank($player, $month);

        return new MonthlyRecap(
            monthName: (string) \IntlDateFormatter::formatObject($month, 'MMMM', 'fr_FR'),
            monthLabel: (string) \IntlDateFormatter::formatObject($month, 'MMMM y', 'fr_FR'),
            quote: $this->movieQuote((int) $month->format('n')),
            rank: $rank,
            tier: RecapTier::fromRank($rank, $points),
            championUsername: $championUsername,
            championGap: $championUsername !== null ? max(0, $championScore - $points) : 0,
            won: $this->achievementRepository->countByPlayerForMonth($player, $month),
            points: $points,
            badges: array_map(static fn (Badge $badge) => $badge->getName(), $this->badgeRepository->findByPlayerForMonth($player, $month)),
            createdPixias: $createdPixias,
            tokens: $tokens,
            tokensMissing: max(0, TokenCost::CREATION_MINIMUM - $tokens),
            tokensPercent: min(100, intdiv($tokens * 100, TokenCost::CREATION_MINIMUM)),
        );
    }

    private function movieQuote(int $month): string
    {
        return match ($month) {
            1 => '« Comment est votre blanquette ? »',
            2 => "« La plus belle chose à apprendre, c'est aimer et être aimé en retour. »",
            3 => '« Vous ne passerez pas ! »',
            4 => '« Élémentaire, mon cher Watson. »',
            5 => '« Que la Force soit avec toi. »',
            6 => '« Bond. James Bond. »',
            7 => '« Hasta la vista, baby. »',
            8 => "« Vers l'infini et au-delà ! »",
            9 => '« Tu es un sorcier, Harry. »',
            10 => '« Quel est ton film d\'horreur préféré ? »',
            11 => "« La vie, c'est comme une boîte de chocolats. »",
            default => '« Pour moi, tu es parfaite. »',
        };
    }
}
