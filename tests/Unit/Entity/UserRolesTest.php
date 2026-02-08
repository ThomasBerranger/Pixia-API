<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

class UserRolesTest extends TestCase
{
    public function testDefaultRolesIncludeUser(): void
    {
        $user = new User();

        self::assertContains(UserRole::USER->value, $user->getRoles());
    }

    public function testBannedUserDoesNotGetRoleUser(): void
    {
        $user = new User();
        $user->setRoles([UserRole::BANNED->value]);

        self::assertContains(UserRole::BANNED->value, $user->getRoles());
        self::assertNotContains(UserRole::USER->value, $user->getRoles());
    }

    public function testBotDoesNotGetRoleUser(): void
    {
        $user = new User();
        $user->setRoles([UserRole::BOT->value]);

        self::assertContains(UserRole::BOT->value, $user->getRoles());
        self::assertNotContains(UserRole::USER->value, $user->getRoles());
    }

    public function testAdminAlsoGetsRoleUser(): void
    {
        $user = new User();
        $user->setRoles([UserRole::ADMIN->value]);

        self::assertContains(UserRole::ADMIN->value, $user->getRoles());
        self::assertContains(UserRole::USER->value, $user->getRoles());
    }

    public function testSuperAdminAlsoGetsRoleUser(): void
    {
        $user = new User();
        $user->setRoles([UserRole::SUPER_ADMIN->value]);

        self::assertContains(UserRole::SUPER_ADMIN->value, $user->getRoles());
        self::assertContains(UserRole::USER->value, $user->getRoles());
    }

    public function testIsBannedReturnsTrueForBannedUser(): void
    {
        $user = new User();
        $user->setRoles([UserRole::BANNED->value]);

        self::assertTrue($user->isBanned());
    }

    public function testIsBannedReturnsFalseForRegularUser(): void
    {
        $user = new User();

        self::assertFalse($user->isBanned());
    }
}
