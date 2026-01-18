<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Service\AchievementService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class AchievementServiceTest extends TestCase
{
    private AchievementService $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new AchievementService($this->entityManager);
    }

    // ========== Tests saveResult() ==========

    public function testSaveResultCreatesAchievement(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) {
                return $achievement instanceof Achievement;
            }));

        $this->service->saveResult($player, $game);
    }

    public function testSaveResultSetsPlayer(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement, $player) {
                $capturedAchievement = $achievement;
                return $achievement->getPlayer() === $player;
            }));

        $this->service->saveResult($player, $game);

        $this->assertSame($player, $capturedAchievement->getPlayer());
    }

    public function testSaveResultSetsGame(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement, $game) {
                $capturedAchievement = $achievement;
                return $achievement->getGame() === $game;
            }));

        $this->service->saveResult($player, $game);

        $this->assertSame($game, $capturedAchievement->getGame());
    }

    public function testSaveResultSetsCurrentDate(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $beforeCall = new \DateTimeImmutable();

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement) {
                $capturedAchievement = $achievement;
                return $achievement->getDate() instanceof \DateTimeImmutable;
            }));

        $this->service->saveResult($player, $game);

        $afterCall = new \DateTimeImmutable();

        // Vérifie que la date est entre avant et après l'appel
        $this->assertGreaterThanOrEqual(
            $beforeCall->getTimestamp(),
            $capturedAchievement->getDate()->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $afterCall->getTimestamp(),
            $capturedAchievement->getDate()->getTimestamp()
        );
    }

    public function testSaveResultSetsDifficultyToEasy(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement) {
                $capturedAchievement = $achievement;
                return $achievement->getDifficulty() === GameDifficulty::EASY;
            }));

        $this->service->saveResult($player, $game);

        $this->assertSame(GameDifficulty::EASY, $capturedAchievement->getDifficulty());
    }

    public function testSaveResultAlwaysUsesEasyDifficulty(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        // Même si le game a une autre difficulté, on met toujours EASY
        $game->method('getDifficulty')->willReturn(GameDifficulty::HARD);

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement) {
                $capturedAchievement = $achievement;
                return true;
            }));

        $this->service->saveResult($player, $game);

        // L'achievement doit avoir EASY, pas HARD du game
        $this->assertSame(GameDifficulty::EASY, $capturedAchievement->getDifficulty());
    }

    public function testSaveResultCallsPersist(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->service->saveResult($player, $game);
    }

    public function testSaveResultDoesNotCallFlush(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->service->saveResult($player, $game);
    }

    // ========== Tests d'intégration ==========

    public function testSaveResultWithMultipleCalls(): void
    {
        $player1 = $this->createMock(User::class);
        $game1 = $this->createMock(Game::class);

        $player2 = $this->createMock(User::class);
        $game2 = $this->createMock(Game::class);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        $this->service->saveResult($player1, $game1);
        $this->service->saveResult($player2, $game2);
    }

    public function testSaveResultCreatesDistinctAchievements(): void
    {
        $player = $this->createMock(User::class);
        $game1 = $this->createMock(Game::class);
        $game2 = $this->createMock(Game::class);

        $achievements = [];
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$achievements) {
                $achievements[] = $achievement;
                return true;
            }));

        $this->service->saveResult($player, $game1);
        $this->service->saveResult($player, $game2);

        // Vérifie que ce sont 2 objets différents
        $this->assertNotSame($achievements[0], $achievements[1]);
        $this->assertSame($game1, $achievements[0]->getGame());
        $this->assertSame($game2, $achievements[1]->getGame());
    }

    public function testSaveResultWithDifferentPlayers(): void
    {
        $player1 = $this->createMock(User::class);
        $player2 = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $achievements = [];
        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$achievements) {
                $achievements[] = $achievement;
                return true;
            }));

        $this->service->saveResult($player1, $game);
        $this->service->saveResult($player2, $game);

        // Vérifie que les 2 achievements ont des players différents
        $this->assertSame($player1, $achievements[0]->getPlayer());
        $this->assertSame($player2, $achievements[1]->getPlayer());
        $this->assertSame($game, $achievements[0]->getGame());
        $this->assertSame($game, $achievements[1]->getGame());
    }

    public function testSaveResultCreatesCompleteAchievement(): void
    {
        $player = $this->createMock(User::class);
        $game = $this->createMock(Game::class);

        $capturedAchievement = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($achievement) use (&$capturedAchievement) {
                $capturedAchievement = $achievement;
                return true;
            }));

        $this->service->saveResult($player, $game);

        // Vérifie que tous les champs sont bien définis
        $this->assertInstanceOf(Achievement::class, $capturedAchievement);
        $this->assertSame($player, $capturedAchievement->getPlayer());
        $this->assertSame($game, $capturedAchievement->getGame());
        $this->assertInstanceOf(\DateTimeImmutable::class, $capturedAchievement->getDate());
        $this->assertSame(GameDifficulty::EASY, $capturedAchievement->getDifficulty());
    }
}
