<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->request($method, $url);

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function authenticatedEndpointsProvider(): iterable
    {
        yield 'me' => ['GET', '/me'];
        yield 'update user' => ['PATCH', '/users'];
        yield 'notifications' => ['GET', '/notifications'];
        yield 'has unread notifications' => ['GET', '/notifications/has-unread'];
        yield 'create contribution' => ['POST', '/contributions'];
    }

    private function getTestUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);
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

        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->request('GET', "/games/{$this->getTestGameId()}/hint");

        self::assertNotEquals(401, $client->getResponse()->getStatusCode());
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
