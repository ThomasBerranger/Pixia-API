<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\MonthlyLeaderboard;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\RecapTier;
use App\Repository\AchievementRepository;
use App\Repository\BadgeRepository;
use App\Repository\GameRepository;
use App\Repository\MonthlyLeaderboardRepository;
use App\Service\MonthlyRecapService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MonthlyRecapServiceTest extends TestCase
{
    private MonthlyRecapService $service;
    private AchievementRepository&MockObject $achievementRepository;
    private MonthlyLeaderboardRepository&MockObject $monthlyLeaderboardRepository;
    private BadgeRepository&MockObject $badgeRepository;
    private GameRepository&MockObject $gameRepository;

    protected function setUp(): void
    {
        $this->achievementRepository = $this->createMock(AchievementRepository::class);
        $this->monthlyLeaderboardRepository = $this->createMock(MonthlyLeaderboardRepository::class);
        $this->badgeRepository = $this->createMock(BadgeRepository::class);
        $this->gameRepository = $this->createMock(GameRepository::class);

        $this->service = new MonthlyRecapService(
            $this->achievementRepository,
            $this->monthlyLeaderboardRepository,
            $this->badgeRepository,
            $this->gameRepository,
        );
    }

    private function player(int $tokens): User&MockObject
    {
        $player = $this->createMock(User::class);
        $player->method('getTokens')->willReturn($tokens);

        return $player;
    }

    private function leaderboardWithScore(int $score): MonthlyLeaderboard&MockObject
    {
        $ml = $this->createMock(MonthlyLeaderboard::class);
        $ml->method('getScore')->willReturn($score);

        return $ml;
    }

    public function testBuildsFullRecap(): void
    {
        $month = new DateTimeImmutable('2026-05-15');

        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn($this->leaderboardWithScore(200));
        $this->monthlyLeaderboardRepository->method('getMonthlyRank')->willReturn(1);
        $this->achievementRepository->method('countByPlayerForMonth')->willReturn(16);

        $badge = $this->createMock(Badge::class);
        $badge->method('getName')->willReturn(BadgeName::WINS_10);
        $this->badgeRepository->method('findByPlayerForMonth')->willReturn([$badge]);

        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix et Inception');
        $game->method('getAttempts')->willReturn(43);
        $this->gameRepository->method('findAuthoredInMonthWithWinners')->willReturn([
            ['game' => $game, 'winners' => 12],
        ]);

        $recap = $this->service->getRecapFor($this->player(700), $month, 'NeoCinephile', 1000);

        $this->assertSame('mai', $recap->monthName);
        $this->assertSame('mai 2026', $recap->monthLabel);
        $this->assertSame(1, $recap->rank);
        $this->assertSame(RecapTier::CHAMPION, $recap->tier);
        $this->assertSame(16, $recap->won);
        $this->assertSame(200, $recap->points);
        $this->assertSame([BadgeName::WINS_10], $recap->badges);
        $this->assertSame(
            [['label' => 'Matrix et Inception', 'guessers' => 12, 'attempts' => 43]],
            $recap->createdPixias,
        );
        // Mai → citation Star Wars (mapping mois → citation, ne doit pas se décaler).
        $this->assertSame('« Que la Force soit avec toi. »', $recap->quote);
    }

    #[DataProvider('quoteByMonthProvider')]
    public function testMovieQuoteDependsOnMonth(string $monthDate, string $expectedQuote): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn(null);

        $recap = $this->service->getRecapFor($this->player(0), new DateTimeImmutable($monthDate), null, 0);

        $this->assertSame($expectedQuote, $recap->quote);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function quoteByMonthProvider(): iterable
    {
        yield 'janvier' => ['2026-01-10', '« Comment est votre blanquette ? »'];
        yield 'mai' => ['2026-05-10', '« Que la Force soit avec toi. »'];
        yield 'décembre' => ['2026-12-10', '« Pour moi, tu es parfaite. »'];
    }

    public function testChampionGapIsDifferenceWithPlayerMonthlyScore(): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn($this->leaderboardWithScore(200));
        $this->monthlyLeaderboardRepository->method('getMonthlyRank')->willReturn(34);

        $recap = $this->service->getRecapFor($this->player(0), new DateTimeImmutable('2026-05-01'), 'Leader', 1250);

        $this->assertSame('Leader', $recap->championUsername);
        $this->assertSame(1050, $recap->championGap); // 1250 - 200
    }

    public function testChampionGapIsZeroWhenNoChampion(): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn(null);

        $recap = $this->service->getRecapFor($this->player(0), new DateTimeImmutable('2026-05-01'), null, 0);

        $this->assertNull($recap->championUsername);
        $this->assertSame(0, $recap->championGap);
    }

    public function testNoMonthlyLeaderboardMeansZeroPointsAndInactiveTier(): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn(null);
        $this->monthlyLeaderboardRepository->method('getMonthlyRank')->willReturn(null);

        $recap = $this->service->getRecapFor($this->player(0), new DateTimeImmutable('2026-05-01'), null, 0);

        $this->assertSame(0, $recap->points);
        $this->assertNull($recap->rank);
        $this->assertSame(RecapTier::INACTIVE, $recap->tier);
    }

    public function testTokensProgressBelowThreshold(): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn(null);

        $recap = $this->service->getRecapFor($this->player(700), new DateTimeImmutable('2026-05-01'), null, 0);

        $this->assertSame(700, $recap->tokens);
        $this->assertSame(300, $recap->tokensMissing); // 1000 - 700
        $this->assertSame(70, $recap->tokensPercent);
    }

    public function testTokensProgressIsCappedAtThreshold(): void
    {
        $this->monthlyLeaderboardRepository->method('findOneBy')->willReturn(null);

        $recap = $this->service->getRecapFor($this->player(1500), new DateTimeImmutable('2026-05-01'), null, 0);

        $this->assertSame(0, $recap->tokensMissing);
        $this->assertSame(100, $recap->tokensPercent);
    }
}
