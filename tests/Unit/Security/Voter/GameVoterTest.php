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

    // ========== Tests authentication ==========

    public function testDeniesWhenUserNotAuthenticated(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testDeniesWhenUserNotAppUserInstance(): void
    {
        $game = $this->createMock(Game::class);
        $token = $this->createMock(TokenInterface::class);
        $nonAppUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $token->method('getUser')->willReturn($nonAppUser);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    // ========== Tests canView() ==========

    public function testGrantsViewToSuperAdmin(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    public function testGrantsViewToAuthor(): void
    {
        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    public function testGrantsViewToSupervisor(): void
    {
        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn($supervisor);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    public function testDeniesViewToAdminWhoIsNotAuthorNorSupervisor(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isSuperAdmin')->willReturn(false);
        $admin->method('isAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn($this->createMock(User::class));

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    public function testDeniesViewToRegularUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    public function testDeniesViewToGameWithNullSupervisor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::VIEW]));
    }

    // ========== Tests canEdit() - not approved ==========

    public function testGrantsEditToAuthorWhenNotApproved(): void
    {
        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('getApprovedAt')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testGrantsEditToSupervisorWhenNotApproved(): void
    {
        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn($supervisor);
        $game->method('getApprovedAt')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testGrantsEditToSuperAdminWhenNotApproved(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);
        $game->method('getApprovedAt')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    // ========== Tests canEdit() - approved ==========

    public function testGrantsEditToSuperAdminWhenApproved(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);
        $game->method('getApprovedAt')->willReturn(new \DateTimeImmutable());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testDeniesEditToAuthorWhenApproved(): void
    {
        $author = $this->createMock(User::class);
        $author->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);
        $game->method('getSupervisor')->willReturn(null);
        $game->method('getApprovedAt')->willReturn(new \DateTimeImmutable());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($author);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testDeniesEditToSupervisorWhenApproved(): void
    {
        $supervisor = $this->createMock(User::class);
        $supervisor->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn($supervisor);
        $game->method('getApprovedAt')->willReturn(new \DateTimeImmutable());

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($supervisor);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }

    public function testDeniesEditToRegularUserWhenNotApproved(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($this->createMock(User::class));
        $game->method('getSupervisor')->willReturn(null);
        $game->method('getApprovedAt')->willReturn(null);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, $game, [GameVoter::EDIT]));
    }
}
