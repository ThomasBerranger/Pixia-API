<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
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
        private ImageService $imageService,
    )
    {
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
            }
        }

        $game->getAuthor()->addBadge(new Badge(BadgeName::CREATOR));

        $event->getObjectManager()->flush();
    }
}
