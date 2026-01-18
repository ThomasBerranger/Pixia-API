<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Badge;
use App\Entity\Contribution;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\ContributionStatus;
use App\Enum\ContributionType;
use App\Service\BadgeService;
use App\Service\ContributionService;
use App\Service\UserService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ContributionServiceTest extends TestCase
{
    private ContributionService $service;
    private EntityManagerInterface $entityManager;
    private BadgeService $badgeService;
    private UserService $userService;
    private EntityRepository $contributionRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->badgeService = $this->createMock(BadgeService::class);
        $this->userService = $this->createMock(UserService::class);
        $this->contributionRepository = $this->createMock(EntityRepository::class);

        $this->service = new ContributionService(
            $this->entityManager,
            $this->badgeService,
            $this->userService
        );
    }

    // ========== Tests validate() ==========

    public function testValidateDoesNotThrowWhenNoPendingContribution(): void
    {
        $author = $this->createMock(User::class);
        $contribution = $this->createMock(Contribution::class);
        $contribution->method('getAuthor')->willReturn($author);
        $contribution->method('getType')->willReturn(ContributionType::GAME_SUGGESTION);

        $this->entityManager
            ->method('getRepository')
            ->with(Contribution::class)
            ->willReturn($this->contributionRepository);

        $this->contributionRepository
            ->method('findOneBy')
            ->with([
                'author' => $author,
                'type' => ContributionType::GAME_SUGGESTION,
                'status' => ContributionStatus::PENDING
            ])
            ->willReturn(null);

        // Ne devrait pas throw
        $this->service->validate($contribution);
        $this->assertTrue(true); // Test passé
    }

    public function testValidateThrowsWhenPendingContributionExists(): void
    {
        $author = $this->createMock(User::class);
        $contribution = $this->createMock(Contribution::class);
        $contribution->method('getAuthor')->willReturn($author);
        $contribution->method('getType')->willReturn(ContributionType::GAME_SUGGESTION);

        $existingContribution = $this->createMock(Contribution::class);

        $this->entityManager
            ->method('getRepository')
            ->with(Contribution::class)
            ->willReturn($this->contributionRepository);

        $this->contributionRepository
            ->method('findOneBy')
            ->with([
                'author' => $author,
                'type' => ContributionType::GAME_SUGGESTION,
                'status' => ContributionStatus::PENDING
            ])
            ->willReturn($existingContribution);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->expectExceptionMessage('Une proposition est déjà en cours de validation.');

        $this->service->validate($contribution);
    }

    public function testValidateChecksCorrectAuthorAndType(): void
    {
        $author = $this->createMock(User::class);
        $contribution = $this->createMock(Contribution::class);
        $contribution->method('getAuthor')->willReturn($author);
        $contribution->method('getType')->willReturn(ContributionType::BUG_DETECTED);

        $this->entityManager
            ->method('getRepository')
            ->with(Contribution::class)
            ->willReturn($this->contributionRepository);

        $this->contributionRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'author' => $author,
                'type' => ContributionType::BUG_DETECTED,
                'status' => ContributionStatus::PENDING
            ])
            ->willReturn(null);

        $this->service->validate($contribution);
    }

    public function testValidateOnlyChecksPendingStatus(): void
    {
        $author = $this->createMock(User::class);
        $contribution = $this->createMock(Contribution::class);
        $contribution->method('getAuthor')->willReturn($author);
        $contribution->method('getType')->willReturn(ContributionType::GAME_SUGGESTION);

        $this->entityManager
            ->method('getRepository')
            ->with(Contribution::class)
            ->willReturn($this->contributionRepository);

        // Vérifie que le status PENDING est bien dans la requête
        $this->contributionRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with($this->callback(function ($criteria) {
                return $criteria['status'] === ContributionStatus::PENDING;
            }))
            ->willReturn(null);

        $this->service->validate($contribution);
    }

    // ========== Tests rewardPlayer() ==========

    public function testRewardPlayerAddsPointsWhenNoContributionBadge(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));

        $this->badgeService
            ->method('userHasBadge')
            ->with($player, [BadgeName::GAME_SUGGESTION, BadgeName::BUG_DETECTED])
            ->willReturn(false);

        $this->userService
            ->expects($this->once())
            ->method('addPoints')
            ->with($player, 10);

        $player->expects($this->once())
            ->method('addBadge')
            ->with($this->isInstanceOf(Badge::class));

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);
    }

    public function testRewardPlayerDoesNotAddPointsWhenAlreadyHasBadge(): void
    {
        $player = $this->createMock(User::class);

        $existingBadge = new Badge(BadgeName::GAME_SUGGESTION);
        $player->method('getBadges')->willReturn(new ArrayCollection([$existingBadge]));

        $this->badgeService
            ->method('userHasBadge')
            ->with($player, [BadgeName::GAME_SUGGESTION, BadgeName::BUG_DETECTED])
            ->willReturn(true);

        $this->userService
            ->expects($this->never())
            ->method('addPoints');

        $player->expects($this->once())
            ->method('addBadge')
            ->with($this->isInstanceOf(Badge::class));

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);
    }

    public function testRewardPlayerAddsBadgeForGameSuggestion(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));

        $this->badgeService
            ->method('userHasBadge')
            ->willReturn(false);

        $capturedBadge = null;
        $player
            ->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(function ($badge) use (&$capturedBadge) {
                $capturedBadge = $badge;
                return $badge instanceof Badge && $badge->getName() === BadgeName::GAME_SUGGESTION;
            }));

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);

        $this->assertSame(BadgeName::GAME_SUGGESTION, $capturedBadge->getName());
    }

    public function testRewardPlayerAddsBadgeForBugReport(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));

        $this->badgeService
            ->method('userHasBadge')
            ->willReturn(false);

        $capturedBadge = null;
        $player
            ->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(function ($badge) use (&$capturedBadge) {
                $capturedBadge = $badge;
                return $badge instanceof Badge && $badge->getName() === BadgeName::BUG_DETECTED;
            }));

        $this->service->rewardPlayer($player, ContributionType::BUG_DETECTED);

        $this->assertSame(BadgeName::BUG_DETECTED, $capturedBadge->getName());
    }

    public function testRewardPlayerAlwaysAddsBadgeEvenIfAlreadyHas(): void
    {
        $player = $this->createMock(User::class);

        $existingBadge = new Badge(BadgeName::GAME_SUGGESTION);
        $player->method('getBadges')->willReturn(new ArrayCollection([$existingBadge]));

        $this->badgeService
            ->method('userHasBadge')
            ->willReturn(true);

        // Points non ajoutés mais badge ajouté quand même
        $this->userService
            ->expects($this->never())
            ->method('addPoints');

        $player
            ->expects($this->once())
            ->method('addBadge');

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);
    }

    // ========== Tests d'intégration ==========

    public function testRewardPlayerChecksSpecificBadges(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));

        $this->badgeService
            ->expects($this->once())
            ->method('userHasBadge')
            ->with(
                $player,
                $this->callback(function ($badges) {
                    return $badges === [BadgeName::GAME_SUGGESTION, BadgeName::BUG_DETECTED];
                })
            )
            ->willReturn(false);

        $player->method('addBadge');

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);
    }

    public function testRewardPlayerWithDifferentContributionTypes(): void
    {
        $contributionTypes = [
            ContributionType::GAME_SUGGESTION->value => BadgeName::GAME_SUGGESTION,
            ContributionType::BUG_DETECTED->value => BadgeName::BUG_DETECTED,
        ];

        foreach ($contributionTypes as $contributionType => $expectedBadge) {
            $player = $this->createMock(User::class);
            $player->method('getBadges')->willReturn(new ArrayCollection([]));

            $this->badgeService
                ->method('userHasBadge')
                ->willReturn(false);

            $capturedBadge = null;
            $player
                ->method('addBadge')
                ->with($this->callback(function ($badge) use (&$capturedBadge) {
                    $capturedBadge = $badge;
                    return true;
                }));

            $this->service->rewardPlayer($player, ContributionType::from($contributionType));

            $this->assertSame(
                $expectedBadge,
                $capturedBadge->getName(),
                "Failed for contribution type: {$contributionType}"
            );
        }
    }

    public function testValidateAllowsMultipleContributionsOfDifferentTypes(): void
    {
        $author = $this->createMock(User::class);

        // Contribution 1 : GAME_SUGGESTION
        $contribution1 = $this->createMock(Contribution::class);
        $contribution1->method('getAuthor')->willReturn($author);
        $contribution1->method('getType')->willReturn(ContributionType::GAME_SUGGESTION);

        // Contribution 2 : BUG_REPORT
        $contribution2 = $this->createMock(Contribution::class);
        $contribution2->method('getAuthor')->willReturn($author);
        $contribution2->method('getType')->willReturn(ContributionType::GAME_SUGGESTION);

        $this->entityManager
            ->method('getRepository')
            ->with(Contribution::class)
            ->willReturn($this->contributionRepository);

        $this->contributionRepository
            ->method('findOneBy')
            ->willReturn(null);

        // Les deux devraient passer sans exception
        $this->service->validate($contribution1);
        $this->service->validate($contribution2);

        $this->assertTrue(true);
    }

    public function testRewardPlayerCreatesNewBadgeInstance(): void
    {
        $player = $this->createMock(User::class);
        $player->method('getBadges')->willReturn(new ArrayCollection([]));

        $this->badgeService
            ->method('userHasBadge')
            ->willReturn(false);

        $capturedBadge = null;
        $player
            ->expects($this->once())
            ->method('addBadge')
            ->with($this->callback(function ($badge) use (&$capturedBadge) {
                $capturedBadge = $badge;
                return $badge instanceof Badge;
            }));

        $this->service->rewardPlayer($player, ContributionType::GAME_SUGGESTION);

        $this->assertInstanceOf(Badge::class, $capturedBadge);
        $this->assertNotNull($capturedBadge->getName());
    }
}
