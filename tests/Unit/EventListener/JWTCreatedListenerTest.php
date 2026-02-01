<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\JWTCreatedListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTCreatedListenerTest extends TestCase
{
    private JWTCreatedListener $listener;

    protected function setUp(): void
    {
        $this->listener = new JWTCreatedListener();
    }

    // ========== Tests payload enrichment ==========

    public function testAddsScoreToPayload(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(150);
        $user->method('getUsername')->willReturn('test');
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $user->method('getAvatarUrl')->willReturn(null);

        $event = new JWTCreatedEvent(['email' => 'test@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertArrayHasKey('score', $payload);
        $this->assertSame(150, $payload['score']);
    }

    public function testAddsUsernameToPayload(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(0);
        $user->method('getUsername')->willReturn('john_doe');
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $user->method('getAvatarUrl')->willReturn(null);

        $event = new JWTCreatedEvent(['email' => 'test@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertArrayHasKey('username', $payload);
        $this->assertSame('john_doe', $payload['username']);
    }

    public function testAddsCreatedAtToPayload(): void
    {
        $createdAt = new \DateTimeImmutable('2025-01-15 10:30:00');

        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(0);
        $user->method('getUsername')->willReturn('test');
        $user->method('getCreatedAt')->willReturn($createdAt);
        $user->method('getAvatarUrl')->willReturn(null);

        $event = new JWTCreatedEvent(['email' => 'test@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertArrayHasKey('createdAt', $payload);
        $this->assertSame($createdAt, $payload['createdAt']);
    }

    public function testAddsAvatarUrlToPayload(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(0);
        $user->method('getUsername')->willReturn('test');
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $user->method('getAvatarUrl')->willReturn('https://example.com/avatar.jpg');

        $event = new JWTCreatedEvent(['email' => 'test@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertArrayHasKey('avatarUrl', $payload);
        $this->assertSame('https://example.com/avatar.jpg', $payload['avatarUrl']);
    }

    public function testAddsNullAvatarUrlToPayload(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(0);
        $user->method('getUsername')->willReturn('test');
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $user->method('getAvatarUrl')->willReturn(null);

        $event = new JWTCreatedEvent(['email' => 'test@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertArrayHasKey('avatarUrl', $payload);
        $this->assertNull($payload['avatarUrl']);
    }

    // ========== Tests existing payload preservation ==========

    public function testPreservesExistingPayloadData(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(100);
        $user->method('getUsername')->willReturn('test');
        $user->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
        $user->method('getAvatarUrl')->willReturn(null);

        $initialPayload = [
            'email' => 'test@test.com',
            'roles' => ['ROLE_USER'],
            'exp' => 1234567890,
        ];

        $event = new JWTCreatedEvent($initialPayload, $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertSame('test@test.com', $payload['email']);
        $this->assertSame(['ROLE_USER'], $payload['roles']);
        $this->assertSame(1234567890, $payload['exp']);
    }

    // ========== Tests non-User instance ==========

    public function testDoesNothingWhenUserNotAppUserInstance(): void
    {
        $nonAppUser = $this->createMock(UserInterface::class);

        $initialPayload = ['email' => 'test@test.com'];
        $event = new JWTCreatedEvent($initialPayload, $nonAppUser);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();
        $this->assertSame($initialPayload, $payload);
        $this->assertArrayNotHasKey('score', $payload);
        $this->assertArrayNotHasKey('username', $payload);
        $this->assertArrayNotHasKey('createdAt', $payload);
        $this->assertArrayNotHasKey('avatarUrl', $payload);
    }

    // ========== Tests all fields added together ==========

    public function testAddsAllFieldsToPayload(): void
    {
        $createdAt = new \DateTimeImmutable('2025-06-01');

        $user = $this->createMock(User::class);
        $user->method('getScore')->willReturn(500);
        $user->method('getUsername')->willReturn('player1');
        $user->method('getCreatedAt')->willReturn($createdAt);
        $user->method('getAvatarUrl')->willReturn('https://cdn.example.com/img.png');

        $event = new JWTCreatedEvent(['email' => 'player@test.com'], $user);

        $this->listener->onJWTCreated($event);

        $payload = $event->getData();

        $this->assertSame('player@test.com', $payload['email']);
        $this->assertSame(500, $payload['score']);
        $this->assertSame('player1', $payload['username']);
        $this->assertSame($createdAt, $payload['createdAt']);
        $this->assertSame('https://cdn.example.com/img.png', $payload['avatarUrl']);
    }
}
