<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AdminEndpointsTest extends WebTestCase
{
    #[DataProvider('adminEndpointsProvider')]
    public function testRequiresAuthentication(string $method, string $url): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('adminEndpointsProvider')]
    public function testDeniesAccessWithRoleUser(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getTestUser());
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('adminEndpointsProvider')]
    public function testAllowsAccessWithRoleAdmin(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getAdminUser());
        $client->request($method, $url);

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [401, 403]);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function adminEndpointsProvider(): iterable
    {
        yield 'games index' => ['GET', '/admin/games?month=2026-01-01'];
        yield 'games create' => ['POST', '/admin/games'];
        yield 'games solution exist' => ['GET', '/admin/games/solution-exist?solution=Inception'];
        yield 'generate content' => ['POST', '/open-api/generate-content'];
    }

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
}
