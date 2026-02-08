<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BannedUserEndpointsTest extends WebTestCase
{
    #[DataProvider('userRoleProtectedEndpointsProvider')]
    public function testDeniesAccessToBannedUser(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(403);
    }

    public static function userRoleProtectedEndpointsProvider(): iterable
    {
        yield 'me' => ['GET', '/me'];
        yield 'update user' => ['PATCH', '/users'];
        yield 'notifications' => ['GET', '/notifications'];
        yield 'has unread notifications' => ['GET', '/notifications/has-unread'];
        yield 'create contribution' => ['POST', '/contributions'];
    }

    public function testDeniesAccessToGameHintForBannedUser(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request('GET', "/games/{$this->getTestGameId()}/hint");

        self::assertResponseStatusCodeSame(403);
    }

    public function testBannedUserCanAccessDailyGame(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request('GET', '/games/daily');

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [401, 403]);
    }

    public function testBannedUserCanAccessLeaderboard(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request('GET', '/users/leaderboard');

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [401, 403]);
    }

    private function getBannedUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'banned@test.com']);
    }

    private function getTestGameId(): string
    {
        $game = self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy([]);

        return $game->getId()->toString();
    }
}
