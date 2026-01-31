<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\TestFixtures;
use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests that admin endpoints require ROLE_ADMIN.
 * Uses real JWT authentication to properly test the stateless firewall.
 */
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

        $token = $this->getTokenForUser($client, 'test@test.com');
        $client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('adminEndpointsProvider')]
    public function testAllowsAccessWithRoleAdmin(string $method, string $url): void
    {
        $client = static::createClient();

        $token = $this->getTokenForUser($client, 'admin@test.com');
        $client->request($method, $url, [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [401, 403]);
    }

    public static function adminEndpointsProvider(): iterable
    {
        yield 'users index' => ['GET', '/admin/users'];
        yield 'games index' => ['GET', '/admin/games'];
        yield 'games create' => ['POST', '/admin/games'];
        yield 'games solution exist' => ['GET', '/admin/games/solution-exist?solution=Inception'];
        yield 'contributions index' => ['GET', '/admin/contributions'];
        yield 'generate content' => ['POST', '/open-api/generate-content'];
        yield 'generate image' => ['POST', '/open-api/generate-image'];
    }

    private function getTokenForUser($client, string $email): string
    {
        $client->jsonRequest('POST', '/login', [
            'email' => $email,
            'password' => TestFixtures::TEST_PASSWORD,
        ]);

        return json_decode($client->getResponse()->getContent(), true)['token'];
    }
}
