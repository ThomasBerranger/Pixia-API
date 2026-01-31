<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
final class JWTCreatedListener
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {}

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        $payload = $event->getData();
        $payload['score'] = $user->getScore();
        $payload['username'] = $user->getUsername();
        $payload['createdAt'] = $user->getCreatedAt();
        $payload['avatarUrl'] = $user->getAvatarUrl();
        $payload['roles'] = $this->roleHierarchy->getReachableRoleNames($user->getRoles());

        $event->setData($payload);
    }
}
