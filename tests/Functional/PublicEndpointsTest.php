<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublicEndpointsTest extends WebTestCase
{
    #[DataProvider('publicEndpointsProvider')]
    public function testPublicEndpointsAreAccessibleWithoutAuth(string $method, string $url): void
    {
        $client = static::createClient();

        $client->jsonRequest($method, $url);

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function publicEndpointsProvider(): iterable
    {
        // Auth
        yield 'register' => ['POST', '/register'];
        yield 'login google' => ['POST', '/google-auth'];
        yield 'forgot password' => ['POST', '/forgot-password'];
        yield 'reset password' => ['POST', '/reset-password'];

        // Games
        yield 'daily game' => ['GET', '/games/daily'];
        yield 'games index' => ['GET', '/games'];

        // Users
        yield 'leaderboard' => ['GET', '/users/leaderboard'];
        yield 'user profile' => ['GET', '/users/test-user'];
    }

    public function testGameSolutionIsPublic(): void
    {
        $client = static::createClient();
        $gameId = self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy([])
            ->getId()
            ->toString();

        $client->jsonRequest('POST', "/games/{$gameId}/solution");

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
    }
}
