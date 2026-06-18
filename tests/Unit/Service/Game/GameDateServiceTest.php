<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Game;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Service\Game\GameDateService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class GameDateServiceTest extends TestCase
{
    public function testAssertUniqueApprovedDateThrowsWhenAnotherApprovedExists(): void
    {
        $repository = $this->createMock(GameRepository::class);
        $repository->method('findApprovedByDate')->willReturn(new Game());

        $service = new GameDateService($repository, new MockClock());

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->assertUniqueApprovedDate(new Game());
    }

    public function testAssertUniqueApprovedDatePassesWhenDateIsFree(): void
    {
        $repository = $this->createMock(GameRepository::class);
        $repository->method('findApprovedByDate')->willReturn(null);

        $service = new GameDateService($repository, new MockClock());

        $this->expectNotToPerformAssertions();
        $service->assertUniqueApprovedDate(new Game());
    }

    public function testValidateDateChangeRejectsNonSupervisor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('isSupervisedBy')->willReturn(false);

        $service = new GameDateService($this->createMock(GameRepository::class), new MockClock('2026-06-17'));

        $this->expectException(AccessDeniedHttpException::class);
        $service->validateDateChange(new DateTimeImmutable('2026-07-01'), $game, $user);
    }

    public function testValidateDateChangeRejectsDateUnderOneWeek(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('isSupervisedBy')->willReturn(true);

        $service = new GameDateService($this->createMock(GameRepository::class), new MockClock('2026-06-17'));

        $this->expectException(UnprocessableEntityHttpException::class);
        $service->validateDateChange(new DateTimeImmutable('2026-06-20'), $game, $user);
    }

    public function testValidateDateChangePassesForSupervisorWithFarEnoughDate(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('isSupervisedBy')->willReturn(true);

        $service = new GameDateService($this->createMock(GameRepository::class), new MockClock('2026-06-17'));

        $this->expectNotToPerformAssertions();
        $service->validateDateChange(new DateTimeImmutable('2026-07-01'), $game, $user);
    }

    public function testValidateDateChangeAllowsSuperAdminWithoutConstraint(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(true);

        $service = new GameDateService($this->createMock(GameRepository::class), new MockClock('2026-06-17'));

        $this->expectNotToPerformAssertions();
        $service->validateDateChange(new DateTimeImmutable('2026-06-17'), $this->createMock(Game::class), $user);
    }
}
