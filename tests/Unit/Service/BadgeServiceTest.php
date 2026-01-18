<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class BadgeServiceTest extends TestCase
{
    private BadgeService $service;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private UserService $userService;
    private EntityRepository $achievementRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->userService = $this->createMock(UserService::class);
        $this->achievementRepository = $this->createMock(EntityRepository::class);

        $this->service = new BadgeService(
            $this->entityManager,
            $this->userRepository,
            $this->userService
        );
    }

    // ========== Tests userHasBadge() ==========

    public function testUserHasBadgeReturnsTrueWhenUserHasBadge(): void
    {
        $badge = new Badge(BadgeName::WINS_1);
        $user = $this->createMock(User::class);

        $badges = new ArrayCollection([$badge]);
        $user->method('getBadges')->willReturn($badges);

        $result = $this->service->userHasBadge($user, [BadgeName::WINS_1]);

        $this->assertTrue($result);
    }

    public function testUserHasBadgeReturnsFalseWhenUserDoesNotHaveBadge(): void
    {
        $user = $this->createMock(User::class);

        $badges = new ArrayCollection([]);
        $user->method('getBadges')->willReturn($badges);

        $result = $this->service->userHasBadge($user, [BadgeName::WINS_1]);

        $this->assertFalse($result);
    }

    public function testUserHasBadgeChecksMultipleBadges(): void
    {
        $badge1 = new Badge(BadgeName::WINS_1);
        $badge2 = new Badge(BadgeName::WINS_10);
        $user = $this->createMock(User::class);

        $badges = new ArrayCollection([$badge1, $badge2]);
        $user->method('getBadges')->willReturn($badges);

        $result = $this->service->userHasBadge($user, [BadgeName::WINS_1, BadgeName::WINS_10, BadgeName::WINS_50]);

        $this->assertTrue($result);
    }

    public function testUserHasBadgeWithEmptyBadgesList(): void
    {
        $user = $this->createMock(User::class);

        $badges = new ArrayCollection([]);
        $user->method('getBadges')->willReturn($badges);

        $result = $this->service->userHasBadge($user, []);

        $this->assertFalse($result);
    }

    // ========== Tests reward() - rewardWin ==========

    public function testRewardCreatesWins1BadgeForFirstWin(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(1);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge && $badge->getName() === BadgeName::WINS_1;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardCreatesWins10BadgeFor10Wins(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(10);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge && $badge->getName() === BadgeName::WINS_10;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardDoesNotCreateBadgeIfAlreadyHas(): void
    {
        $existingBadge = new Badge(BadgeName::WINS_1);
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([$existingBadge]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(1);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    // ========== Tests reward() - rewardTopTenReached ==========

    public function testRewardCreatesTopTenBadgeWhenInTopTen(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(0);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(5); // Top 10
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge && $badge->getName() === BadgeName::TOP_10_REACHED;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardDoesNotCreateTopTenBadgeWhenNotInTopTen(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(0);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(15); // Pas top 10
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    // ========== Tests reward() - rewardSponsor ==========

    public function testRewardGivesPointsToSponsorAt10Wins(): void
    {
        $sponsor = $this->createMock(User::class);
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn($sponsor);

        $this->achievementRepository->method('count')->willReturn(10);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->userService
            ->expects($this->once())
            ->method('addPoints')
            ->with($sponsor, 5);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardDoesNotRewardSponsorIfNoSponsor(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(10);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->userService
            ->expects($this->never())
            ->method('addPoints');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardDoesNotRewardSponsorIfNot10Wins(): void
    {
        $sponsor = $this->createMock(User::class);
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn($sponsor);

        $this->achievementRepository->method('count')->willReturn(9);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->userService
            ->expects($this->never())
            ->method('addPoints');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    // ========== Tests reward() - rewardStreak ==========

    public function testRewardCreatesStreak10BadgeForStreak10(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(0);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 10, 'best' => 10]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge && $badge->getName() === BadgeName::STREAK_10;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardCreatesStreak30BadgeForStreak30(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(0);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 30, 'best' => 30]);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge && $badge->getName() === BadgeName::STREAK_30;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    // ========== Tests d'intégration ==========

    public function testRewardCallsFlushOnce(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(0);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->reward($player);
    }

    public function testRewardHandlesMultipleBadgesAtOnce(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        // 10 wins = WINS_10 badge
        // Rank 5 = TOP_10_REACHED badge
        // Streak 10 = STREAK_10 badge
        $this->achievementRepository->method('count')->willReturn(10);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(5);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 10, 'best' => 10]);

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('persist');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);
    }

    public function testRewardSetsPlayerOnBadge(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));
        $player->method('getSponsor')->willReturn(null);

        $this->achievementRepository->method('count')->willReturn(1);

        $this->entityManager
            ->method('getRepository')
            ->with(Achievement::class)
            ->willReturn($this->achievementRepository);

        $this->userRepository->method('getRank')->willReturn(20);
        $this->userRepository->method('getStreaks')->willReturn(['current' => 0, 'best' => 0]);

        $capturedBadge = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($badge) use (&$capturedBadge, $player) {
                $capturedBadge = $badge;
                return $badge->getPlayer() === $player;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->reward($player);

        $this->assertSame($player, $capturedBadge->getPlayer());
    }
}
