<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Contribution;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use Symfony\Bundle\SecurityBundle\Security;

#[AsEntityListener(event: Events::prePersist, method: 'prePersist', entity: Contribution::class)]
readonly class ContributionListener
{
    public function __construct(
        private Security $security,
    )
    {
    }

    public function prePersist(Contribution $contribution, PrePersistEventArgs $event): void
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $contribution->setAuthor($user);
        }
    }
}
