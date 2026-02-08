<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Game;
use App\Entity\User;
use App\Security\Voter\UserVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class UserVoterTest extends TestCase
{
    private UserVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new UserVoter();
    }

    // ========== Tests supports() ==========

    public function testSupportsViewAttributeWithUser(): void
    {
        $targetUser = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $user, ['edit']);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNonUserSubject(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $game, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNullSubject(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, null, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ========== Tests voteOnAttribute() - Authentication ==========

    public function testDeniesViewWhenUserNotAuthenticated(): void
    {
        $targetUser = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewWhenUserNotAppUserInstance(): void
    {
        $targetUser = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);

        // Create a UserInterface that is NOT an App\Entity\User
        $nonAppUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $token->method('getUser')->willReturn($nonAppUser);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========== Tests voteOnAttribute() - SuperAdmin access ==========

    public function testGrantsViewToSuperAdmin(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);
        $superAdmin->method('getUserIdentifier')->willReturn('superadmin@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('other@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSuperAdminCanViewAnyUser(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);
        $superAdmin->method('getUserIdentifier')->willReturn('superadmin@test.com');

        $targetUser1 = $this->createMock(User::class);
        $targetUser1->method('getUserIdentifier')->willReturn('user1@test.com');

        $targetUser2 = $this->createMock(User::class);
        $targetUser2->method('getUserIdentifier')->willReturn('user2@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result1 = $this->voter->vote($token, $targetUser1, [UserVoter::VIEW]);
        $result2 = $this->voter->vote($token, $targetUser2, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result1);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result2);
    }

    public function testSuperAdminViewingSelf(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);
        $superAdmin->method('getUserIdentifier')->willReturn('superadmin@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $superAdmin, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ========== Tests voteOnAttribute() - Admin access ==========

    public function testGrantsViewToAdmin(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isSuperAdmin')->willReturn(false);
        $admin->method('isAdmin')->willReturn(true);
        $admin->method('getUserIdentifier')->willReturn('admin@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('other@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testAdminCanViewAnyUser(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isSuperAdmin')->willReturn(false);
        $admin->method('isAdmin')->willReturn(true);
        $admin->method('getUserIdentifier')->willReturn('admin@test.com');

        $targetUser1 = $this->createMock(User::class);
        $targetUser1->method('getUserIdentifier')->willReturn('user1@test.com');

        $targetUser2 = $this->createMock(User::class);
        $targetUser2->method('getUserIdentifier')->willReturn('user2@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result1 = $this->voter->vote($token, $targetUser1, [UserVoter::VIEW]);
        $result2 = $this->voter->vote($token, $targetUser2, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result1);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result2);
    }

    // ========== Tests voteOnAttribute() - Self access ==========

    public function testGrantsViewToSelf(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(false);
        $user->method('getUserIdentifier')->willReturn('user@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // Target is the same user
        $result = $this->voter->vote($token, $user, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsViewWhenSameUserIdentifier(): void
    {
        $currentUser = $this->createMock(User::class);
        $currentUser->method('isAdmin')->willReturn(false);
        $currentUser->method('getUserIdentifier')->willReturn('same@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('same@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($currentUser);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ========== Tests voteOnAttribute() - Denied access ==========

    public function testDeniesViewToOtherUserWhenNotAdmin(): void
    {
        $currentUser = $this->createMock(User::class);
        $currentUser->method('isAdmin')->willReturn(false);
        $currentUser->method('getUserIdentifier')->willReturn('current@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('target@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($currentUser);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewWhenDifferentEmails(): void
    {
        $currentUser = $this->createMock(User::class);
        $currentUser->method('isAdmin')->willReturn(false);
        $currentUser->method('getUserIdentifier')->willReturn('alice@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('bob@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($currentUser);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========== Tests edge cases ==========

    public function testIsCaseSensitiveForUserIdentifier(): void
    {
        $currentUser = $this->createMock(User::class);
        $currentUser->method('isAdmin')->willReturn(false);
        $currentUser->method('getUserIdentifier')->willReturn('User@Test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('user@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($currentUser);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);

        // Should be denied because identifiers are case-sensitive
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testAdminViewingSelf(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isSuperAdmin')->willReturn(false);
        $admin->method('isAdmin')->willReturn(true);
        $admin->method('getUserIdentifier')->willReturn('admin@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $admin, [UserVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsViewToUserWithBothSuperAdminAndAdmin(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(true);
        $user->method('isAdmin')->willReturn(true);
        $user->method('getUserIdentifier')->willReturn('both@test.com');

        $targetUser = $this->createMock(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('other@test.com');

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $targetUser, [UserVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }
}
