<?php

namespace App\EventListener;

use App\Entity\Game;
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
        private ImageService $imageService
    )
    {
    }

    public function prePersist(Game $game, PrePersistEventArgs $event): void
    {
        $game->setAuthor($this->security->getUser());
    }

    public function postPersist(Game $game, PostPersistEventArgs $event): void
    {
        if ($game->getImageUrl()) {
            $game->setImage(
                $this->imageService->downloadAndStore(
                    $game->getType(),
                    $game->getDate(),
                    $game->getImageUrl()
                )
            );

            $event->getObjectManager()->flush();
        }
    }
}
