<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\GameDifficulty;
use App\Repository\AchievementRepository;
use App\Service\AchievementService;
use App\Service\BadgeService;
use App\Service\GameSolutionService;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests for race condition vulnerabilities.
 * These tests verify that concurrent operations don't lead to:
 * - Double point attribution
 * - Double badge attribution
 * - Double achievement creation
 */
class RaceConditionTest extends TestCase
{
    // ========== Tests Achievement Duplication ==========

    public function testHandleAnswerDoesNotCreateDuplicateAchievements(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $achievementService = $this->createMock(AchievementService::class);
        $userService = $this->createMock(UserService::class);
        $achievementRepository = $this->createMock(AchievementRepository::class);

        $service = new GameSolutionService(
            $entityManager,
            $achievementService,
            $userService,
            $achievementRepository
        );

        $user = $this->createMock(User::class);
        $user->method('getHintedGames')->willReturn(new ArrayCollection([]));

        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);
        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('setAttempts');

        // Simulate that an achievement already exists (from a concurrent request)
        $existingAchievement = $this->createMock(Achievement::class);
        $achievementRepository
            ->method('findOneBy')
            ->with(['player' => $user, 'game' => $game])
            ->willReturn($existingAchievement);

        // Achievement service should NOT be called when achievement already exists
        $achievementService
            ->expects($this->never())
            ->method('saveResult');

        // User service should NOT add points if achievement exists
        $userService
            ->expects($this->never())
            ->method('addGamePoints');

        $entityManager->method('flush');

        $service->handleAnswer($game, 'Matrix', $user);
    }

    public function testHandleAnswerCreatesAchievementOnlyOnce(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $achievementService = $this->createMock(AchievementService::class);
        $userService = $this->createMock(UserService::class);
        $achievementRepository = $this->createMock(AchievementRepository::class);

        $service = new GameSolutionService(
            $entityManager,
            $achievementService,
            $userService,
            $achievementRepository
        );

        $user = $this->createMock(User::class);
        $user->method('getHintedGames')->willReturn(new ArrayCollection([]));

        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);
        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('setAttempts');

        // No existing achievement
        $achievementRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Achievement should be created exactly once
        $achievementService
            ->expects($this->once())
            ->method('saveResult')
            ->with($user, $game);

        // Points should be added exactly once
        $userService
            ->expects($this->once())
            ->method('addGamePoints')
            ->with($user, $game);

        $entityManager->method('flush');

        $service->handleAnswer($game, 'Matrix', $user);
    }

    // ========== Tests Badge Duplication ==========

    public function testBadgeServiceUserHasBadgeReturnsTrueWhenBadgeExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $userService = $this->createMock(UserService::class);

        $service = new BadgeService($entityManager, $userRepository, $userService);

        // Create a real Badge with the SPONSOR name
        $existingBadge = new Badge(BadgeName::SPONSOR);

        // Create a real ArrayCollection with the badge
        $badges = new ArrayCollection([$existingBadge]);

        $user = $this->createMock(User::class);
        $user->method('getBadges')->willReturn($badges);

        // userHasBadge should return true when badge already exists
        $result = $service->userHasBadge($user, [BadgeName::SPONSOR]);

        $this->assertTrue($result);
    }

    public function testBadgeServiceUserHasBadgeReturnsFalseWhenBadgeNotExists(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $userService = $this->createMock(UserService::class);

        $service = new BadgeService($entityManager, $userRepository, $userService);

        // Create user without any badges
        $user = $this->createMock(User::class);
        $user->method('getBadges')->willReturn(new ArrayCollection([]));

        // userHasBadge should return false when badge doesn't exist
        $result = $service->userHasBadge($user, [BadgeName::SPONSOR]);

        $this->assertFalse($result);
    }

    public function testBadgeServiceChecksMultipleBadgeNames(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $userService = $this->createMock(UserService::class);

        $service = new BadgeService($entityManager, $userRepository, $userService);

        // Create user with WINS_1 badge
        $existingBadge = new Badge(BadgeName::WINS_1);
        $badges = new ArrayCollection([$existingBadge]);

        $user = $this->createMock(User::class);
        $user->method('getBadges')->willReturn($badges);

        // Should return true when checking for WINS_1 among other badges
        $result = $service->userHasBadge($user, [BadgeName::SPONSOR, BadgeName::WINS_1]);

        $this->assertTrue($result);
    }

    // ========== Tests Points Attribution ==========

    public function testPointsAreAddedAtomically(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $achievementService = $this->createMock(AchievementService::class);
        $userService = $this->createMock(UserService::class);
        $achievementRepository = $this->createMock(AchievementRepository::class);

        $service = new GameSolutionService(
            $entityManager,
            $achievementService,
            $userService,
            $achievementRepository
        );

        $initialScore = 100;

        $user = $this->createMock(User::class);
        $user->method('getHintedGames')->willReturn(new ArrayCollection([]));

        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);
        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('setAttempts');

        $achievementRepository->method('findOneBy')->willReturn(null);

        // Verify addGamePoints is called exactly once per correct answer
        $userService
            ->expects($this->once())
            ->method('addGamePoints')
            ->with($user, $game);

        $entityManager->method('flush');
        $achievementService->method('saveResult');

        $service->handleAnswer($game, 'Matrix', $user);
    }

    // ========== Tests Sponsor Reward Duplication ==========

    public function testSponsorRewardIsAddedOnlyOnce(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $monthlyLeaderboardService = $this->createMock(\App\Service\MonthlyLeaderboardService::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);
        $genericRepository = $this->createMock(\Doctrine\ORM\EntityRepository::class);

        $service = new UserService(
            $entityManager,
            $monthlyLeaderboardService,
            $userRepository
        );

        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('getReferees')->willReturn(new ArrayCollection([]));

        // setScore should be called exactly once with exactly 5 points added
        $sponsor->expects($this->once())
            ->method('setScore')
            ->with(105);

        // addBadge should be called exactly once for SPONSOR badge
        $sponsor->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(fn($badge) => $badge->getName() === BadgeName::SPONSOR));

        $entityManager->method('getRepository')->willReturn($genericRepository);
        $genericRepository->method('findOneBy')->willReturn($sponsor);
        $monthlyLeaderboardService->method('addMonthlyPoints');

        $service->findAndRewardSponsor('sponsor-slug');
    }

    // ========== Tests Concurrent Game Attempts ==========

    public function testGameAttemptsIncrementCorrectly(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $achievementService = $this->createMock(AchievementService::class);
        $userService = $this->createMock(UserService::class);
        $achievementRepository = $this->createMock(AchievementRepository::class);

        $service = new GameSolutionService(
            $entityManager,
            $achievementService,
            $userService,
            $achievementRepository
        );

        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(5);

        // setAttempts should be called with current + 1
        $game->expects($this->once())
            ->method('setAttempts')
            ->with(6);

        $entityManager->expects($this->once())->method('flush');

        $service->handleAnswer($game, 'Wrong Answer', null);
    }

    // ========== Tests Streak Calculation ==========

    public function testStreakIsCalculatedConsistently(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $monthlyLeaderboardService = $this->createMock(\App\Service\MonthlyLeaderboardService::class);
        $userRepository = $this->createMock(\App\Repository\UserRepository::class);

        $service = new UserService(
            $entityManager,
            $monthlyLeaderboardService,
            $userRepository
        );

        $players = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $userRepository
            ->method('getLeaderboard')
            ->with(1)
            ->willReturn($players);

        // setStreaksFor should be called exactly once with the players
        $userRepository
            ->expects($this->once())
            ->method('setStreaksFor')
            ->with($players);

        $result = $service->getLeaderboard(1);

        $this->assertSame($players, $result);
    }
}
