<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;

class UserRolesTest extends TestCase
{
    public function testNormalUserContainsRoleUser(): void
    {
        $user = new User();
        $user->setEmail('normal@test.com')
            ->setUsername('normal-user')
            ->setPassword('hashed');

        $roles = $user->getRoles();

        $this->assertContains(UserRole::USER->value, $roles);
    }

    public function testBannedUserDoesNotContainRoleUser(): void
    {
        $user = new User();
        $user->setEmail('banned@test.com')
            ->setUsername('banned-user')
            ->setPassword('hashed')
            ->setRoles([UserRole::BANNED->value]);

        $roles = $user->getRoles();

        $this->assertNotContains(UserRole::USER->value, $roles);
        $this->assertContains(UserRole::BANNED->value, $roles);
    }

    public function testBotUserDoesNotContainRoleUser(): void
    {
        $user = new User();
        $user->setEmail('bot@test.com')
            ->setUsername('bot-user')
            ->setPassword('hashed')
            ->setRoles([UserRole::BOT->value]);

        $roles = $user->getRoles();

        $this->assertNotContains(UserRole::USER->value, $roles);
        $this->assertContains(UserRole::BOT->value, $roles);
    }

    public function testAdminUserContainsRoleUserAndRoleAdmin(): void
    {
        $user = new User();
        $user->setEmail('admin@test.com')
            ->setUsername('admin-user')
            ->setPassword('hashed')
            ->setRoles([UserRole::ADMIN->value]);

        $roles = $user->getRoles();

        $this->assertContains(UserRole::USER->value, $roles);
        $this->assertContains(UserRole::ADMIN->value, $roles);
    }

    public function testSuperAdminUserContainsRoleUserAndRoleSuperAdmin(): void
    {
        $user = new User();
        $user->setEmail('superadmin@test.com')
            ->setUsername('super-admin-user')
            ->setPassword('hashed')
            ->setRoles([UserRole::SUPER_ADMIN->value]);

        $roles = $user->getRoles();

        $this->assertContains(UserRole::USER->value, $roles);
        $this->assertContains(UserRole::SUPER_ADMIN->value, $roles);
    }

    public function testRolesAreUnique(): void
    {
        $user = new User();
        $user->setEmail('user@test.com')
            ->setUsername('user')
            ->setPassword('hashed')
            ->setRoles([UserRole::USER->value]);

        $roles = $user->getRoles();

        $this->assertCount(1, array_filter($roles, fn (string $role) => $role === UserRole::USER->value));
    }
}
