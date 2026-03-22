<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\GameDifficulty;
use App\Repository\UserRepository;
use App\Service\MonthlyLeaderboardService;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserServiceTest extends TestCase
{
    private UserService $service;
    private EntityManagerInterface&MockObject $entityManager;
    private MonthlyLeaderboardService&MockObject $monthlyLeaderboardService;
    private UserRepository&MockObject $userRepository;
    /** @var EntityRepository<User>&MockObject */
    private EntityRepository&MockObject $genericUserRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->monthlyLeaderboardService = $this->createMock(MonthlyLeaderboardService::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->genericUserRepository = $this->createMock(EntityRepository::class);

        $this->service = new UserService(
            $this->entityManager,
            $this->monthlyLeaderboardService,
            $this->userRepository
        );
    }

    // ========== Tests addGamePoints() ==========

    public function testAddGamePointsCalculatesPointsFromDifficulty(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(100);
        $player->method('getHintedGames')->willReturn(new ArrayCollection([]));

        $game = $this->createMock(Game::class);
        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('getDate')->willReturn(new \DateTimeImmutable());

        // EASY sans hint = 10 points → score attendu 110
        $player->expects($this->once())
            ->method('setScore')
            ->with(110);

        $this->monthlyLeaderboardService
            ->expects($this->once())
            ->method('addMonthlyPoints');

        $this->service->addGamePoints($player, $game);
    }

    public function testAddGamePointsConsidersHintUsage(): void
    {
        $game = $this->createMock(Game::class);
        $hintedGames = new ArrayCollection([$game]);

        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(50);
        $player->method('getHintedGames')->willReturn($hintedGames);

        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('getDate')->willReturn(new \DateTimeImmutable());

        // EASY avec hint = 5 points → score attendu 55 (pas 60)
        $player->expects($this->once())
            ->method('setScore')
            ->with(55);

        $this->monthlyLeaderboardService
            ->expects($this->once())
            ->method('addMonthlyPoints');

        $this->service->addGamePoints($player, $game);
    }

    public function testAddGamePointsPassesGameDateToMonthlyLeaderboard(): void
    {
        $gameDate = new \DateTimeImmutable('2025-01-15');

        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(100);
        $player->method('getHintedGames')->willReturn(new ArrayCollection([]));

        $game = $this->createMock(Game::class);
        $game->method('getDifficulty')->willReturn(GameDifficulty::EASY);
        $game->method('getDate')->willReturn($gameDate);

        $player->method('setScore');

        $this->monthlyLeaderboardService
            ->expects($this->once())
            ->method('addMonthlyPoints')
            ->with(
                $player,
                $this->anything(),
                $gameDate
            );

        $this->service->addGamePoints($player, $game);
    }

    // ========== Tests addPoints() ==========

    public function testAddPointsIncreasesPlayerScore(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(100);

        $player->expects($this->once())
            ->method('setScore')
            ->with(125);

        $this->service->addPoints($player, 25);
    }

    public function testAddPointsDoesNotCallMonthlyLeaderboardService(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(50);
        $player->method('setScore');

        $this->monthlyLeaderboardService
            ->expects($this->never())
            ->method('addMonthlyPoints');

        $this->service->addPoints($player, 10);
    }

    public function testAddPointsDoesNotFlush(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(100);
        $player->method('setScore');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->service->addPoints($player, 5);
    }

    // ========== Tests findAndRewardSponsor() ==========

    public function testFindAndRewardSponsorReturnsFoundSponsor(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore');
        $sponsor->method('addBadge');

        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->with(['slug' => 'test-sponsor'])
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->willReturn(0);

        $result = $this->service->findAndRewardSponsor('test-sponsor');

        $this->assertSame($sponsor, $result);
    }

    public function testFindAndRewardSponsorThrowsWhenNotFound(): void
    {
        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->with(['slug' => 'non-existent'])
            ->willReturn(null);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Sponsor not found');

        $this->service->findAndRewardSponsor('non-existent');
    }

    public function testFindAndRewardSponsorAdds5PointsWithoutAffectingMonthly(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(50);
        $sponsor->method('addBadge');

        $sponsor->expects($this->once())
            ->method('setScore')
            ->with(55);

        $this->entityManager
            ->method('getRepository')
            ->with(User::class)
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->willReturn(0);

        $this->monthlyLeaderboardService
            ->expects($this->never())
            ->method('addMonthlyPoints');

        $this->service->findAndRewardSponsor('sponsor-slug');
    }

    public function testFindAndRewardSponsorAddsSponsorBadge(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore');

        $sponsor->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(function ($badge) {
                return $badge instanceof Badge
                    && $badge->getName() === BadgeName::SPONSOR;
            }));

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->with($sponsor)
            ->willReturn(0);

        $this->service->findAndRewardSponsor('sponsor-slug');
    }

    public function testFindAndRewardSponsorAddsSponsor5BadgeWhen5Referees(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore');

        $badgesAdded = [];
        $sponsor->expects($this->exactly(2))
            ->method('addBadge')
            ->willReturnCallback(function ($badge) use (&$badgesAdded, $sponsor) {
                $badgesAdded[] = $badge->getName();
                return $sponsor;
            });

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->with($sponsor)
            ->willReturn(5);

        $this->service->findAndRewardSponsor('sponsor-slug');

        $this->assertContains(BadgeName::SPONSOR, $badgesAdded);
        $this->assertContains(BadgeName::SPONSOR_5, $badgesAdded);
    }

    public function testFindAndRewardSponsorDoesNotAddSponsor5BadgeWhenLessThan5Referees(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore');

        $sponsor->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(fn ($badge) => $badge->getName() === BadgeName::SPONSOR));

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->with($sponsor)
            ->willReturn(2);

        $this->service->findAndRewardSponsor('sponsor-slug');
    }

    // ========== Tests getLeaderboard() ==========

    public function testGetLeaderboardReturnsPlayers(): void
    {
        $players = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $this->userRepository
            ->method('getLeaderboard')
            ->with(1)
            ->willReturn($players);

        $this->userRepository
            ->expects($this->once())
            ->method('setStreaksFor')
            ->with($players);

        $result = $this->service->getLeaderboard(1);

        $this->assertSame($players, $result);
    }

    public function testGetLeaderboardCallsSetStreaksFor(): void
    {
        $players = [$this->createMock(User::class)];

        $this->userRepository
            ->method('getLeaderboard')
            ->willReturn($players);

        $this->userRepository
            ->expects($this->once())
            ->method('setStreaksFor')
            ->with($players);

        $this->service->getLeaderboard(1);
    }

    // ========== Tests getCurrentMonthLeaderboard() ==========

    public function testGetCurrentMonthLeaderboardReturnsPlayers(): void
    {
        $date = new \DateTimeImmutable('2025-01-15');
        $players = [
            $this->createMock(User::class),
            $this->createMock(User::class),
        ];

        $this->userRepository
            ->method('getMonthlyLeaderboard')
            ->with($date)
            ->willReturn($players);

        $this->userRepository
            ->expects($this->once())
            ->method('setStreaksFor')
            ->with($players);

        $result = $this->service->getCurrentMonthLeaderboard($date);

        $this->assertSame($players, $result);
    }

    public function testGetCurrentMonthLeaderboardCallsSetStreaksFor(): void
    {
        $date = new \DateTimeImmutable('2025-02-01');
        $players = [$this->createMock(User::class)];

        $this->userRepository
            ->method('getMonthlyLeaderboard')
            ->willReturn($players);

        $this->userRepository
            ->expects($this->once())
            ->method('setStreaksFor')
            ->with($players);

        $this->service->getCurrentMonthLeaderboard($date);
    }

    // ========== Tests d'intégration ==========

    public function testFindAndRewardSponsorDoesNotAffectMonthlyLeaderboard(): void
    {
        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore')->with(105);
        $sponsor->method('addBadge');

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->willReturn(0);

        $this->monthlyLeaderboardService
            ->expects($this->never())
            ->method('addMonthlyPoints');

        $result = $this->service->findAndRewardSponsor('sponsor-slug');

        $this->assertSame($sponsor, $result);
    }

    public function testServiceDoesNotFlushAnywhere(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getScore')->willReturn(100);
        $player->method('setScore');

        $this->entityManager->expects($this->never())->method('flush');

        $this->service->addPoints($player, 10);

        $sponsor = $this->createMock(User::class);
        $sponsor->method('getScore')->willReturn(100);
        $sponsor->method('setScore');
        $sponsor->method('addBadge');

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->genericUserRepository);

        $this->genericUserRepository
            ->method('findOneBy')
            ->willReturn($sponsor);

        $this->userRepository
            ->method('countReferees')
            ->willReturn(0);

        $this->service->findAndRewardSponsor('test');
    }
}
