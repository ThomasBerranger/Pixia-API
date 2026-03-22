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
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[DataProvider('badgeProvider')]
    public function testCreateBadgeNotificationSetsAllFieldsCorrectly(BadgeName $badgeName): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge($badgeName);
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

        $this->assertSame($player, $capturedNotification->getPlayer());
        $this->assertSame(NotificationType::BADGE, $capturedNotification->getType());
        $this->assertSame($badgeName->value, $capturedNotification->getTitle());
        $this->assertSame($badgeName->value, $capturedNotification->getContent());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function badgeProvider(): iterable
    {
        yield 'wins' => [BadgeName::WINS_1];
        yield 'streak' => [BadgeName::STREAK_30];
        yield 'sponsor' => [BadgeName::SPONSOR_5];
        yield 'contribution' => [BadgeName::GAME_SUGGESTION];
        yield 'creator' => [BadgeName::CREATOR];
    }

    public function testCreateBadgeNotificationPersistsBeforeFlush(): void
    {
        $player = $this->createMock(User::class);
        $badge = new Badge(BadgeName::WINS_1);
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

        $this->assertSame(['persist', 'flush'], $callOrder);
    }
}
