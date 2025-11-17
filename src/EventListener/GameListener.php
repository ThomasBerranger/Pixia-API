<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Game;
use App\Entity\User;
use App\Service\BadgeService;
use App\Service\ImageService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Game::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Game::class)]
readonly class GameListener
{
    public function __construct(
        private Security     $security,
        private ImageService $imageService, private BadgeService $badgeService,
    ) {
    }

    public function prePersist(Game $game, PrePersistEventArgs $event): void
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $game->setAuthor($user);
        }
    }

    public function postPersist(Game $game, PostPersistEventArgs $event): void
    {
        $imageUrl = $game->getImageUrl();

        if ($imageUrl) {
            $imageName = $this->imageService->downloadAndStore($game->getType(), $game->getDate(), $imageUrl);

            if ($imageName) {
                $game->setImage($imageName);
                $event->getObjectManager()->flush();
            }
        }

        $this->badgeService->checkAndAwardCreatorBadge($game->getAuthor());
    }
}
