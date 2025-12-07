<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Badge;
use App\Entity\Notification;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;

readonly class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function createBadgeNotification(Badge $badge): void
    {
        $notification = new Notification();

        $notification
            ->setPlayer($badge->getPlayer())
            ->setType(NotificationType::BADGE)
            ->setTitle($badge->getName()->value)
            ->setContent($badge->getName()->value);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
