<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Badge;
use App\Service\NotificationService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Badge::class)]
readonly class BadgeListener
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function postPersist(Badge $badge, PostPersistEventArgs $event): void
    {
        $this->notificationService->createBadgeNotification($badge);
    }
}
