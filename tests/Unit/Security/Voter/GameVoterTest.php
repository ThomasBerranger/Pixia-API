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

    // ========== Tests voteOnAttribute() - VIEW ==========

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

        // Create a UserInterface that is NOT an App\Entity\User
        $nonAppUser = $this->createMock(\Symfony\Component\Security\Core\User\UserInterface::class);
        $token->method('getUser')->willReturn($nonAppUser);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testGrantsViewWhenAdminAndAuthor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesViewWhenAdminButNotAuthor(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isAdmin')->willReturn(true);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewWhenAuthorButNotAdmin(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesViewWhenNeitherAdminNorAuthor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(false);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    // ========== Tests voteOnAttribute() - EDIT ==========

    public function testGrantsEditWhenAdminAndAuthor(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testDeniesEditWhenAdminButNotAuthor(): void
    {
        $admin = $this->createMock(User::class);
        $admin->method('isAdmin')->willReturn(true);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($admin);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testDeniesEditWhenAuthorButNotAdmin(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(false);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($user);

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

    // ========== Tests voteOnAttribute() - SuperAdmin ==========

    public function testGrantsViewWhenSuperAdmin(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testGrantsEditWhenSuperAdmin(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    public function testSuperAdminCanEditAnyGame(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);

        $author1 = $this->createMock(User::class);
        $author2 = $this->createMock(User::class);

        $game1 = $this->createMock(Game::class);
        $game1->method('getAuthor')->willReturn($author1);

        $game2 = $this->createMock(Game::class);
        $game2->method('getAuthor')->willReturn($author2);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result1 = $this->voter->vote($token, $game1, [GameVoter::EDIT]);
        $result2 = $this->voter->vote($token, $game2, [GameVoter::EDIT]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result1);
        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result2);
    }

    public function testSuperAdminCanViewAnyGame(): void
    {
        $superAdmin = $this->createMock(User::class);
        $superAdmin->method('isSuperAdmin')->willReturn(true);
        $superAdmin->method('isAdmin')->willReturn(false);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($superAdmin);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        $this->assertEquals(VoterInterface::ACCESS_GRANTED, $result);
    }

    // ========== Tests edge cases ==========

    public function testDeniesWhenUserIsNotGameAuthor(): void
    {
        $currentUser = $this->createMock(User::class);
        $currentUser->method('isAdmin')->willReturn(true);

        $differentAuthor = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($differentAuthor);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($currentUser);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);

        // Even admin cannot view/edit if not author
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }

    public function testViewAndEditUsesSameLogic(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isAdmin')->willReturn(true);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($user);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $viewResult = $this->voter->vote($token, $game, [GameVoter::VIEW]);
        $editResult = $this->voter->vote($token, $game, [GameVoter::EDIT]);

        $this->assertEquals($viewResult, $editResult);
    }

    public function testDeniesViewToRegularUser(): void
    {
        $user = $this->createMock(User::class);
        $user->method('isSuperAdmin')->willReturn(false);
        $user->method('isAdmin')->willReturn(false);

        $author = $this->createMock(User::class);

        $game = $this->createMock(Game::class);
        $game->method('getAuthor')->willReturn($author);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, $game, [GameVoter::VIEW]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);

        $result = $this->voter->vote($token, $game, [GameVoter::EDIT]);
        $this->assertEquals(VoterInterface::ACCESS_DENIED, $result);
    }
}
