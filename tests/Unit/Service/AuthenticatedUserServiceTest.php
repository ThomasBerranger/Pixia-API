<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\AuthenticatedUserService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class AuthenticatedUserServiceTest extends TestCase
{
    private AuthenticatedUserService $service;
    private Security&MockObject $security;

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
            ->method('getUser')
            ->willReturn($user);

        $this->assertSame($user, $this->service->getUser());
    }

    public function testGetUserReturnsNullWhenNotAuthenticated(): void
    {
        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->assertNull($this->service->getUser());
    }

    public function testGetUserReturnsNullWhenUserIsNotAppUser(): void
    {
        $otherUser = $this->createMock(UserInterface::class);

        $this->security
            ->method('getUser')
            ->willReturn($otherUser);

        $this->assertNull($this->service->getUser());
    }

    // ========== Tests getUserOrThrow() ==========

    public function testGetUserOrThrowReturnsUserWhenAuthenticated(): void
    {
        $user = $this->createMock(User::class);

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->assertSame($user, $this->service->getUserOrThrow());
    }

    public function testGetUserOrThrowThrowsExceptionWhenNotAuthenticated(): void
    {
        $this->security
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
            ->method('getUser')
            ->willReturn($otherUser);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('User must be authenticated');

        $this->service->getUserOrThrow();
    }
}
