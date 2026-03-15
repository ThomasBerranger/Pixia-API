<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\TokenCost;
use App\Repository\ActivityRepository;
use App\Service\ActivityService;
use App\Service\TokenService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TokenServiceTest extends TestCase
{
    private TokenService $service;
    private ActivityRepository&MockObject $activityRepository;
    private ActivityService&MockObject $activityService;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->activityRepository = $this->createMock(ActivityRepository::class);
        $this->activityService = $this->createMock(ActivityService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new TokenService(
            $this->activityRepository,
            $this->activityService,
            $this->entityManager,
        );
    }

    // ========== Tests removeTokens() ==========

    public function testRemoveTokensCallsUserRemoveTokens(): void
    {
        $user = $this->createMock(User::class);
        $user->expects($this->once())
            ->method('removeTokens')
            ->with(TokenCost::IDEA_ANALYSIS->value);

        $this->service->removeTokens($user, TokenCost::IDEA_ANALYSIS);
    }

    public function testRemoveTokensThrowsWhenInsufficientTokens(): void
    {
        $user = $this->createMock(User::class);
        $user->method('removeTokens')->willThrowException(new \LogicException());

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->removeTokens($user, TokenCost::IDEA_ANALYSIS);
    }

    // ========== Tests canClaimTokens() ==========

    public function testCanClaimTokensReturnsTrueWhenNeverClaimed(): void
    {
        $player = $this->createMock(User::class);

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn(null);

        $this->assertTrue($this->service->canClaimTokens($player));
    }

    public function testCanClaimTokensReturnsTrueWhenCooldownPassed(): void
    {
        $player = $this->createMock(User::class);

        $activity = $this->createMock(Activity::class);
        $activity->method('getCreatedAt')
            ->willReturn(new DateTimeImmutable('-6 days'));

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn($activity);

        $this->assertTrue($this->service->canClaimTokens($player));
    }

    public function testCanClaimTokensReturnsDateWhenCooldownNotPassed(): void
    {
        $player = $this->createMock(User::class);

        $activity = $this->createMock(Activity::class);
        $activity->method('getCreatedAt')
            ->willReturn(new DateTimeImmutable('-2 days'));

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn($activity);

        $result = $this->service->canClaimTokens($player);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    public function testCanClaimTokensReturnedDateIsInFuture(): void
    {
        $player = $this->createMock(User::class);

        $activity = $this->createMock(Activity::class);
        $activity->method('getCreatedAt')
            ->willReturn(new DateTimeImmutable('-1 day'));

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn($activity);

        $result = $this->service->canClaimTokens($player);

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertGreaterThan(new DateTimeImmutable(), $result);
    }

    // ========== Tests canClaimAdminTokens() ==========

    public function testCanClaimAdminTokensReturnsFalseForNonAdmin(): void
    {
        $player = $this->createMock(User::class);
        $player->method('isAdmin')->willReturn(false);

        $this->assertFalse($this->service->canClaimAdminTokens($player));
    }

    public function testCanClaimAdminTokensReturnsTrueForAdminNotClaimed(): void
    {
        $player = $this->createMock(User::class);
        $player->method('isAdmin')->willReturn(true);

        $this->activityRepository
            ->method('hasClaimedThisMonth')
            ->willReturn(false);

        $this->assertTrue($this->service->canClaimAdminTokens($player));
    }

    public function testCanClaimAdminTokensReturnsFalseForAdminAlreadyClaimed(): void
    {
        $player = $this->createMock(User::class);
        $player->method('isAdmin')->willReturn(true);

        $this->activityRepository
            ->method('hasClaimedThisMonth')
            ->willReturn(true);

        $this->assertFalse($this->service->canClaimAdminTokens($player));
    }

    // ========== Tests claimTokens() ==========

    public function testClaimTokensAddsTokensAndFlushes(): void
    {
        $player = $this->createMock(User::class);

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn(null);

        $player->expects($this->once())->method('addTokens');
        $this->activityService->expects($this->once())->method('recordPlayerTokensClaim');
        $this->entityManager->expects($this->once())->method('flush');

        $this->service->claimTokens($player);
    }

    public function testClaimTokensReturnsMultipleOf5(): void
    {
        $player = $this->createMock(User::class);

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn(null);

        $amount = $this->service->claimTokens($player);

        $this->assertSame(0, $amount % 5);
    }

    public function testClaimTokensReturnsAmountBetween5And180(): void
    {
        $player = $this->createMock(User::class);

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn(null);

        $amount = $this->service->claimTokens($player);

        $this->assertGreaterThanOrEqual(5, $amount);
        $this->assertLessThanOrEqual(180, $amount);
    }

    public function testClaimTokensThrowsWhenCooldownNotPassed(): void
    {
        $player = $this->createMock(User::class);

        $activity = $this->createMock(Activity::class);
        $activity->method('getCreatedAt')
            ->willReturn(new DateTimeImmutable('-1 day'));

        $this->activityRepository
            ->method('findLastByPlayerAndType')
            ->willReturn($activity);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->claimTokens($player);
    }

    // ========== Tests claimAdminTokens() ==========

    public function testClaimAdminTokensAdds995Tokens(): void
    {
        $player = $this->createMock(User::class);
        $player->method('isAdmin')->willReturn(true);

        $this->activityRepository
            ->method('hasClaimedThisMonth')
            ->willReturn(false);

        $player->expects($this->once())->method('addTokens')->with(995);
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->claimAdminTokens($player);

        $this->assertSame(995, $result);
    }

    public function testClaimAdminTokensThrowsWhenAlreadyClaimed(): void
    {
        $player = $this->createMock(User::class);
        $player->method('isAdmin')->willReturn(true);

        $this->activityRepository
            ->method('hasClaimedThisMonth')
            ->willReturn(true);

        $this->expectException(UnprocessableEntityHttpException::class);

        $this->service->claimAdminTokens($player);
    }
}
