<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserGameAccessTest extends WebTestCase
{
    private function getTestUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);
    }

    private function getAdminUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@test.com']);
    }

    private function getSuperAdminUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'superadmin@test.com']);
    }

    private function createGameForUser(User $author, GameStatus $status = GameStatus::DRAFT, ?User $supervisor = null): Game
    {
        $doctrine = self::getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $game = new Game();
        $game->setSolution('Test Game Solution')
            ->setEnglishSolution('Test Game Solution')
            ->setHint('Test hint')
            ->setDate(new \DateTimeImmutable('+2 weeks'))
            ->setType(GameType::MULTIVERS)
            ->setAuthor($author)
            ->setStatus($status)
            ->setImage('test.jpg');

        if ($supervisor !== null) {
            $game->setSupervisor($supervisor);
        }

        $em->persist($game);
        $em->flush();
        $em->clear();

        return $game;
    }

    public function testAuthorCanViewOwnGame(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $game = $this->createGameForUser($user);

        $client->loginUser($user);
        $client->request('GET', "/me/games/{$game->getId()}");

        self::assertResponseStatusCodeSame(200);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals($game->getId()->toString(), $responseData['id']);
    }

    public function testAuthorCanEditOwnGame(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $game = $this->createGameForUser($user);

        $client->loginUser($user);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Updated Solution',
            'hint' => 'Updated hint'
        ]);

        self::assertResponseStatusCodeSame(200);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('Updated Solution', $responseData['solution']);
    }

    public function testNonAuthorCannotViewGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $otherUser = $this->getAdminUser();
        $game = $this->createGameForUser($author);

        $client->loginUser($otherUser);
        $client->request('GET', "/me/games/{$game->getId()}");

        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAuthorCannotEditGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $otherUser = $this->getAdminUser();
        $game = $this->createGameForUser($author);

        $client->loginUser($otherUser);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Hacked Solution',
            'hint' => 'Hacked hint'
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSupervisorCanViewGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $supervisor = $this->getAdminUser();
        $game = $this->createGameForUser($author, GameStatus::PENDING, $supervisor);

        $client->loginUser($supervisor);
        $client->request('GET', "/me/games/{$game->getId()}");

        self::assertResponseStatusCodeSame(200);
    }

    public function testSupervisorCanEditDraftGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $supervisor = $this->getAdminUser();
        $game = $this->createGameForUser($author, GameStatus::DRAFT, $supervisor);

        $client->loginUser($supervisor);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Supervisor Updated',
            'hint' => 'Supervisor hint'
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testSupervisorCannotEditApprovedGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $supervisor = $this->getAdminUser();
        $game = $this->createGameForUser($author, GameStatus::APPROVED, $supervisor);

        $client->loginUser($supervisor);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Supervisor Updated',
            'hint' => 'Supervisor hint'
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanViewAnyGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $superAdmin = $this->getSuperAdminUser();
        $game = $this->createGameForUser($author);

        $client->loginUser($superAdmin);
        $client->request('GET', "/me/games/{$game->getId()}");

        self::assertResponseStatusCodeSame(200);
    }

    public function testSuperAdminCanEditAnyGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $superAdmin = $this->getSuperAdminUser();
        $game = $this->createGameForUser($author);

        $client->loginUser($superAdmin);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Super Admin Updated',
            'hint' => 'Super Admin hint'
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testSuperAdminCanEditApprovedGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $superAdmin = $this->getSuperAdminUser();
        $game = $this->createGameForUser($author, GameStatus::APPROVED);

        $client->loginUser($superAdmin);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Super Admin Approved Edit',
            'hint' => 'Super Admin approved hint'
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    public function testAuthorCannotEditApprovedGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $game = $this->createGameForUser($author, GameStatus::APPROVED);

        $client->loginUser($author);
        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Author trying to edit approved',
            'hint' => 'Author hint'
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnauthenticatedUserCannotViewGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $game = $this->createGameForUser($author);

        $client->request('GET', "/me/games/{$game->getId()}");

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnauthenticatedUserCannotEditGame(): void
    {
        $client = static::createClient();
        $author = $this->getTestUser();
        $game = $this->createGameForUser($author);

        $client->jsonRequest('PATCH', "/me/games/{$game->getId()}", [
            'solution' => 'Unauthorized edit',
            'hint' => 'Unauthorized hint'
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
