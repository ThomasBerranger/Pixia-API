<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\GameStatus;
use App\Service\GameDateService;
use App\Service\GameStatusService;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Game::class)]
#[AsEntityListener(event: Events::preUpdate, method: 'preUpdate', entity: Game::class)]
#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Game::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate', entity: Game::class)]
readonly class GameListener
{
    public function __construct(
        private Security          $security,
        private ImageService      $imageService,
        private GameStatusService $gameStatusService,
        private GameDateService   $gameDateService,
    )
    {
    }

    public function prePersist(Game $game, PrePersistEventArgs $event): void
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $game->setAuthor($user);

            if ($user->isAdmin() || $user->isSuperAdmin()) {
                $game->setSupervisor($user);
            }
        }

        $game->setDate(new DateTimeImmutable('+2 weeks')->setTime(0, 0));
    }

    public function preUpdate(Game $game, PreUpdateEventArgs $event): void
    {
        $user = $this->security->getUser();

        if(!$user instanceof User) {
            return;
        }

        if ($event->hasChangedField('status')) {
            $this->gameStatusService->validateStatusTransition(
                $game,
                GameStatus::fromMixed($event->getOldValue('status')),
                GameStatus::fromMixed($event->getNewValue('status')),
                $user
            );
        }

        if ($event->hasChangedField('date')) {
            $newDate = $event->getNewValue('date');

            if ($newDate instanceof DateTimeImmutable) {
                $this->gameDateService->validateDateChange($newDate, $game, $user);
            }
        }
    }

    public function postPersist(Game $game, PostPersistEventArgs $event): void
    {
        $this->handleImageDownload($game, $event->getObjectManager());

        $game->getAuthor()->addBadge(new Badge(BadgeName::CREATOR));

        $event->getObjectManager()->flush();
    }

    public function postUpdate(Game $game, PostUpdateEventArgs $event): void
    {
        $this->handleImageDownload($game, $event->getObjectManager());
    }

    private function handleImageDownload(Game $game, $objectManager): void
    {
        $imageUrl = $game->getImageUrl();

        if ($imageUrl && !$game->getImage()) {
            $imageName = $this->imageService->downloadAndStore($game->getType(), $game->getDate(), $imageUrl);

            if ($imageName) {
                $game->setImage($imageName);
                $objectManager->flush();
            }
        }
    }
}
