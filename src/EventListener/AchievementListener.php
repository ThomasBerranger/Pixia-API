<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Achievement;
use App\Service\BadgeService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Achievement::class)]
readonly class AchievementListener
{
    public function __construct(private BadgeService $badgeService)
    {
    }

    public function postPersist(Achievement $achievement, PostPersistEventArgs $event): void
    {
        $this->badgeService->checkAndAwardBadges($achievement->getPlayer());
    }
}
