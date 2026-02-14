<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security\Voter;

use App\Entity\Game;
use App\Entity\User;
use App\Security\Voter\GameVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

class GameVoterTest extends TestCase
{
    private GameVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new GameVoter();
    }

    // ========== Tests supports() ==========

    public function testSupportsViewAttributeWithGame(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testSupportsEditAttributeWithGame(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertNotEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForUnknownAttribute(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $game, ['unknown_attribute']);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNonGameSubject(): void
    {
        $user = $this->createMock(User::class);
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, $user, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    public function testAbstainsForNullSubject(): void
    {
        $token = $this->createMock(TokenInterface::class);

        $result = $this->voter->vote($token, null, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    // ========== Tests canView() ==========

    public function testDeniesViewWhenUserNotAuthenticated(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewWhenUserNotAppUserInstance(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);
        $nonAppUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $token->method('getUser')->willReturn($nonAppUser);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsViewToSuperAdmin(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $author = $this->createMock(User::class);
        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsViewToAuthor(): void
    {
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsViewToSupervisor(): void
    {
        $supervisorId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);
        $supervisor->method('getId')->willReturn($supervisorId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn($supervisor);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesViewToAdminNotAuthorNorSupervisor(): void
    {
        $adminId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $admin = $this->createMock(User::class);
        $admin->method('isSuperAdmin')->willReturn(false);
        $admin->method('getId')->willReturn($adminId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewToRegularUser(): void
    {
        $userId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('getId')->willReturn($userId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========== Tests canEdit() - not approved ==========

    public function testGrantsEditToAuthorWhenNotApproved(): void
    {
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('isApproved')->willReturn(false);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsEditToSupervisorWhenNotApproved(): void
    {
        $supervisorId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);
        $supervisor->method('getId')->willReturn($supervisorId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn($supervisor);
        $game->method('isApproved')->willReturn(false);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsEditToSuperAdminWhenNotApproved(): void
    {
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('isApproved')->willReturn(false);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ========== Tests canEdit() - approved ==========

    public function testGrantsEditToSuperAdminWhenApproved(): void
    {
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('isApproved')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesEditToAuthorWhenApproved(): void
    {
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('isApproved')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesEditToSupervisorWhenApproved(): void
    {
        $supervisorId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);
        $supervisor->method('getId')->willReturn($supervisorId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn($supervisor);
        $game->method('isApproved')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesEditToRegularUserWhenApproved(): void
    {
        $userId = \Symfony\Component\Uid\Uuid::v7();
        $authorId = \Symfony\Component\Uid\Uuid::v7();

        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('getId')->willReturn($userId);

        $author = $this->createMock(User::class);
        $author->method('getId')->willReturn($authorId);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('isApproved')->willReturn(true);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesEditWhenNotAuthenticated(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
