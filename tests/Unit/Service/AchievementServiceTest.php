<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Repository\AchievementRepository;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AchievementServiceTest extends TestCase
{
    private AchievementService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private AchievementRepository&MockObject $achievementRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->achievementRepository = $this->createMock(AchievementRepository::class);
        $this->service = new AchievementService($this->entityManager, $this->achievementRepository);
    }

    // ========== Tests saveResult() ==========

    public function testSaveResultPersistsCompleteAchievement(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createGameMock(GameDifficulty::HARD);

        $beforeCall = new \DateTimeImmutable();

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement) {
                $capturedAchievement = $achievement;
                return $achievement instanceof Achievement;
            }));

        $this->service->saveResult($player, $game);

        $afterCall = new \DateTimeImmutable();

        $this->assertSame($player, $capturedAchievement->getPlayer());
        $this->assertSame($game, $capturedAchievement->getGame());
        $this->assertSame(GameDifficulty::HARD, $capturedAchievement->getDifficulty());
        $this->assertGreaterThanOrEqual($beforeCall->getTimestamp(), $capturedAchievement->getDate()->getTimestamp());
        $this->assertLessThanOrEqual($afterCall->getTimestamp(), $capturedAchievement->getDate()->getTimestamp());
    }

    public function testSaveResultCreatesDistinctAchievements(): void
    {
        $player1 = $this->createMock(User::class);
        $player2 = $this->createMock(User::class);
        $game1 = $this->createGameMock();
        $game2 = $this->createGameMock();

        $achievements = [];
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$achievements) {
                $achievements[] = $achievement;
                return true;
            }));

        $this->service->saveResult($player1, $game1);
        $this->service->saveResult($player2, $game2);

        $this->assertNotSame($achievements[0], $achievements[1]);
        $this->assertSame($player1, $achievements[0]->getPlayer());
        $this->assertSame($player2, $achievements[1]->getPlayer());
        $this->assertSame($game1, $achievements[0]->getGame());
        $this->assertSame($game2, $achievements[1]->getGame());
    }

    // ========== Tests getRecentPointsHistory() ==========

    public function testGetRecentPointsHistoryReturns14Days(): void
    {
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([]);

        $result = $this->service->getRecentPointsHistory($player);

        $this->assertCount(14, $result);
    }

    public function testGetRecentPointsHistoryReturnsCustomDays(): void
    {
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([]);

        $result = $this->service->getRecentPointsHistory($player, 7);

        $this->assertCount(7, $result);
    }

    public function testGetRecentPointsHistoryDatesAreChronological(): void
    {
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([]);

        $result = $this->service->getRecentPointsHistory($player);

        $dates = array_column($result, 'date');
        $sorted = $dates;
        sort($sorted);

        $this->assertSame($sorted, $dates);
    }

    public function testGetRecentPointsHistoryLastDateIsToday(): void
    {
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([]);

        $result = $this->service->getRecentPointsHistory($player);

        $this->assertSame((new \DateTimeImmutable('today'))->format('Y-m-d'), end($result)['date']);
    }

    public function testGetRecentPointsHistoryZeroPointsWhenNoData(): void
    {
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([]);

        $result = $this->service->getRecentPointsHistory($player);

        foreach ($result as $entry) {
            $this->assertSame(0, $entry['points']);
        }
    }

    public function testGetRecentPointsHistoryCalculatesPointsForWonGame(): void
    {
        $player = $this->createMock(User::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([
                ['date' => $today, 'difficulty' => 'SIMPLE', 'won' => '1', 'hinted' => '0'],
            ]);

        $result = $this->service->getRecentPointsHistory($player);

        $todayEntry = end($result);
        $this->assertSame(10, $todayEntry['points']);
    }

    public function testGetRecentPointsHistoryCalculatesHintedPoints(): void
    {
        $player = $this->createMock(User::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([
                ['date' => $today, 'difficulty' => 'DIABOLIQUE', 'won' => '1', 'hinted' => '1'],
            ]);

        $result = $this->service->getRecentPointsHistory($player);

        $todayEntry = end($result);
        $this->assertSame(25, $todayEntry['points']);
    }

    public function testGetRecentPointsHistoryZeroPointsForLostGame(): void
    {
        $player = $this->createMock(User::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([
                ['date' => $today, 'difficulty' => 'TORDU', 'won' => '0', 'hinted' => '0'],
            ]);

        $result = $this->service->getRecentPointsHistory($player);

        $todayEntry = end($result);
        $this->assertSame(0, $todayEntry['points']);
    }

    public function testGetRecentPointsHistorySumsMultipleGamesSameDay(): void
    {
        $player = $this->createMock(User::class);
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');

        $this->achievementRepository
            ->method('getRecentHistory')
            ->willReturn([
                ['date' => $today, 'difficulty' => 'SIMPLE', 'won' => '1', 'hinted' => '0'],
                ['date' => $today, 'difficulty' => 'TORDU', 'won' => '1', 'hinted' => '0'],
            ]);

        $result = $this->service->getRecentPointsHistory($player);

        $todayEntry = end($result);
        $this->assertSame(35, $todayEntry['points']); // 10 + 25
    }

    private function createGameMock(GameDifficulty $difficulty = GameDifficulty::EASY): Game&\PHPUnit\Framework\MockObject\MockObject
    {
        $game = $this->createMock(Game::class);
        $game->method('getDifficulty')->willReturn($difficulty);

        return $game;
    }
}
