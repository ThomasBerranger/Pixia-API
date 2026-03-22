<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class BannedUserEndpointsTest extends WebTestCase
{
    #[DataProvider('userProtectedEndpointsProvider')]
    public function testBannedUserGetsForbiddenOnUserRoutes(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('publicEndpointsProvider')]
    public function testBannedUserCanAccessPublicRoutes(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getBannedUser());
        $client->request($method, $url);

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [403, 500], "Banned user on public route {$method} {$url} returned {$statusCode}");
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function userProtectedEndpointsProvider(): iterable
    {
        yield 'dashboard' => ['GET', '/me'];
        yield 'games stats' => ['GET', '/me/games-stats'];
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function publicEndpointsProvider(): iterable
    {
        yield 'daily game' => ['GET', '/games/daily'];
    }

    private function getBannedUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'banned@test.com']);
    }
}
