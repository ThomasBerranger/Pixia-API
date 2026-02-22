<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\NotificationType;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    private NotificationService $service;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new NotificationService($this->entityManager);
    }

    // ========== Tests createBadgeNotification() ==========

    public function testCreateBadgeNotificationCreatesNotification(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::WINS_1);
        $badge->setPlayer($player);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Notification::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->createBadgeNotification($badge);
    }

    public function testCreateBadgeNotificationSetsPlayer(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::WINS_10);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification, $player) {
                $capturedNotification = $notification;
                return $notification instanceof Notification
                    && $notification->getPlayer() === $player;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame($player, $capturedNotification->getPlayer());
    }

    public function testCreateBadgeNotificationSetsTypeToBadge(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::STREAK_10);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return $notification instanceof Notification
                    && $notification->getType() === NotificationType::BADGE;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(NotificationType::BADGE, $capturedNotification->getType());
    }

    public function testCreateBadgeNotificationSetsTitleFromBadgeName(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::TOP_10_REACHED);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return $notification instanceof Notification;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(BadgeName::TOP_10_REACHED->value, $capturedNotification->getTitle());
    }

    public function testCreateBadgeNotificationSetsContentFromBadgeName(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::CREATOR);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return $notification instanceof Notification;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(BadgeName::CREATOR->value, $capturedNotification->getContent());
    }

    public function testCreateBadgeNotificationCallsPersist(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::SPONSOR);
        $badge->setPlayer($player);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);
    }

    public function testCreateBadgeNotificationCallsFlush(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::BUG_DETECTED);
        $badge->setPlayer($player);

        $this->entityManager->expects($this->once())->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->createBadgeNotification($badge);
    }

    // ========== Tests avec différents badges ==========

    public function testCreateBadgeNotificationWithWinsBadge(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::WINS_50);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(BadgeName::WINS_50->value, $capturedNotification->getTitle());
        $this->assertSame(BadgeName::WINS_50->value, $capturedNotification->getContent());
    }

    public function testCreateBadgeNotificationWithStreakBadge(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::STREAK_30);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(BadgeName::STREAK_30->value, $capturedNotification->getTitle());
        $this->assertSame(BadgeName::STREAK_30->value, $capturedNotification->getContent());
    }

    public function testCreateBadgeNotificationWithSponsorBadge(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::SPONSOR_5);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        $this->assertSame(BadgeName::SPONSOR_5->value, $capturedNotification->getTitle());
        $this->assertSame(BadgeName::SPONSOR_5->value, $capturedNotification->getContent());
    }

    // ========== Tests d'intégration ==========

    public function testCreateBadgeNotificationSetsAllFields(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::GAME_SUGGESTION);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        // Vérifie que tous les champs sont bien définis
        $this->assertInstanceOf(Notification::class, $capturedNotification);
        $this->assertSame($player, $capturedNotification->getPlayer());
        $this->assertSame(NotificationType::BADGE, $capturedNotification->getType());
        $this->assertSame(BadgeName::GAME_SUGGESTION->value, $capturedNotification->getTitle());
        $this->assertSame(BadgeName::GAME_SUGGESTION->value, $capturedNotification->getContent());
    }

    public function testCreateBadgeNotificationTitleAndContentAreSame(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::BETA_TESTER);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        // Title et Content doivent être identiques
        $this->assertSame(
            $capturedNotification->getTitle(),
            $capturedNotification->getContent()
        );
    }

    public function testCreateBadgeNotificationWithMultipleBadges(): void
    {
        $player = $this->createMock(User::class);

        $badge1 = new Badge(BadgeName::WINS_1);
        $badge1->setPlayer($player);

        $badge2 = new Badge(BadgeName::WINS_10);
        $badge2->setPlayer($player);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('flush');

        $this->service->createBadgeNotification($badge1);
        $this->service->createBadgeNotification($badge2);
    }

    public function testCreateBadgeNotificationUsesEnumValue(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::WINS_100);
        $badge->setPlayer($player);

        $capturedNotification = null;
        $this->entityManager
            ->method('persist')
            ->with($this->callback(function ($notification) use (&$capturedNotification) {
                $capturedNotification = $notification;
                return true;
            }));

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->createBadgeNotification($badge);

        // Vérifie que c'est bien la valeur string de l'enum, pas l'enum lui-même
        $this->assertIsString($capturedNotification->getTitle());
        $this->assertIsString($capturedNotification->getContent());
    }

    public function testCreateBadgeNotificationPersistsBeforeFlush(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::CREATOR);
        $badge->setPlayer($player);

        $callOrder = [];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'persist';
            });

        $this->entityManager
            ->expects($this->once())
            ->method('flush')
            ->willReturnCallback(function () use (&$callOrder) {
                $callOrder[] = 'flush';
            });

        $this->service->createBadgeNotification($badge);

        // Vérifie que persist est appelé avant flush
        $this->assertSame(['persist', 'flush'], $callOrder);
    }
}
