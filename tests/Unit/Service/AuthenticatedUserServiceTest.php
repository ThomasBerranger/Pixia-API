<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\AuthenticatedUserService;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticatedUserServiceTest extends TestCase
{
    private AuthenticatedUserService $service;
    private Security $security;

    protected function setUp(): void
    {
        $this->security = $this->createMock(Security::class);
        $this->service = new AuthenticatedUserService($this->security);
    }

    // ========== Tests getUser() ==========

    public function testGetUserReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $result = $this->service->getUser();

        $this->assertSame($user, $result);
    }

    public function testGetUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $result = $this->service->getUser();

        $this->assertNull($result);
    }

    public function testGetUserReturnsNullWhenUserIsNotAppUser(): void
    {
        // Simule un UserInterface qui n'est pas notre entité User
        $otherUser = $this->createMock(UserInterface::class);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($otherUser);

        $result = $this->service->getUser();

        $this->assertNull($result);
    }

    public function testGetUserCallsSecurityOnce(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->service->getUser();
    }

    public function testGetUserWithDifferentUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user2 = $this->createMock(User::class);

        $this->security
            ->method('getUser')
            ->willReturnOnConsecutiveCalls($user1, $user2);

        $result1 = $this->service->getUser();
        $result2 = $this->service->getUser();

        $this->assertSame($user1, $result1);
        $this->assertSame($user2, $result2);
    }

    // ========== Tests getUserOrThrow() ==========

    public function testGetUserOrThrowReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $result = $this->service->getUserOrThrow();

        $this->assertSame($user, $result);
    }

    public function testGetUserOrThrowThrowsExceptionWhenNotAuthenticated(): void
    {
        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $this->service->getUserOrThrow();
    }

    public function testGetUserOrThrowThrowsExceptionWhenUserIsNotAppUser(): void
    {
        $otherUser = $this->createMock(UserInterface::class);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($otherUser);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $this->service->getUserOrThrow();
    }

    public function testGetUserOrThrowDoesNotThrowWhenUserExists(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->method('getUser')
            ->willReturn($user);

        // Ne devrait pas throw
        $result = $this->service->getUserOrThrow();

        $this->assertInstanceOf(User::class, $result);
    }

    // ========== Tests d'intégration ==========

    public function testGetUserAndGetUserOrThrowConsistency(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $getUserResult = $this->service->getUser();
        $getUserOrThrowResult = $this->service->getUserOrThrow();

        // Les deux méthodes doivent retourner le même user
        $this->assertSame($getUserResult, $getUserOrThrowResult);
    }

    public function testMultipleCallsToGetUser(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $result1 = $this->service->getUser();
        $result2 = $this->service->getUser();
        $result3 = $this->service->getUser();

        // Toutes les calls retournent le même user
        $this->assertSame($user, $result1);
        $this->assertSame($user, $result2);
        $this->assertSame($user, $result3);
    }

    public function testGetUserOrThrowUsesGetUserInternally(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        // getUserOrThrow doit appeler getUser() qui appelle security->getUser()
        $result = $this->service->getUserOrThrow();

        $this->assertSame($user, $result);
    }

    public function testExceptionMessageIsDescriptive(): void
    {
        $this->security
            ->method('getUser')
            ->willReturn(null);

        try {
            $this->service->getUserOrThrow();
            $this->fail('Expected LogicException was not thrown');
        } catch (\LogicException $e) {
            $this->assertEquals('User must be authenticated', $e->getMessage());
        }
    }

    // ========== Tests cas limites ==========

    public function testGetUserHandlesNullGracefully(): void
    {
        $this->security
            ->method('getUser')
            ->willReturn(null);

        // Ne devrait pas throw, juste retourner null
        $result = $this->service->getUser();

        $this->assertNull($result);
    }

    public function testGetUserOrThrowCanBeCaughtAndHandled(): void
    {
        $this->security
            ->method('getUser')
            ->willReturn(null);

        $exceptionCaught = false;

        try {
            $this->service->getUserOrThrow();
        } catch (\LogicException $e) {
            $exceptionCaught = true;
        }

        $this->assertTrue($exceptionCaught);
    }

    public function testServiceWorksWithRealUserInstance(): void
    {
        // Simule un vrai User (pas un mock complet)
        $user = new User();

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $result = $this->service->getUser();

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($user, $result);
    }
}
