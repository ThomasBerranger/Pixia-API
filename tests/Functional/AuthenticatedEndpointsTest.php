<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\TestFixtures;
use App\Entity\Game;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests that endpoints require authentication.
 * Uses real JWT authentication to properly test the stateless firewall.
 */
class AuthenticatedEndpointsTest extends WebTestCase
{
    #[DataProvider('authenticatedEndpointsProvider')]
    public function testRequiresAuthentication(string $method, string $url): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('authenticatedEndpointsProvider')]
    public function testAllowsAccessWithValidUser(string $method, string $url): void
    {
        $client = static::createClient();

        $token = $this->getTestUserToken($client);

        $client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public static function authenticatedEndpointsProvider(): iterable
    {
        yield 'me' => ['GET', '/me'];
        yield 'update user' => ['PATCH', '/users'];
        yield 'notifications' => ['GET', '/notifications'];
        yield 'has unread notifications' => ['GET', '/notifications/has-unread'];
        yield 'create contribution' => ['POST', '/contributions'];
    }

    public function testGameEndpointsRequireAuth(): void
    {
        $client = static::createClient();

        $client->request('GET', "/games/{$this->getTestGameId()}/hint");

        self::assertResponseStatusCodeSame(401);
    }

    public function testGameHintWithAuthenticatedUser(): void
    {
        $client = static::createClient();

        $token = $this->getTestUserToken($client);

        $client->request('GET', "/games/{$this->getTestGameId()}/hint", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    private function getTestUserToken($client): string
    {
        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => TestFixtures::TEST_PASSWORD,
        ]);

        return json_decode($client->getResponse()->getContent(), true)['token'];
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
