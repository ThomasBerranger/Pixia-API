<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SuperAdminEndpointsTest extends WebTestCase
{
    #[DataProvider('superAdminEndpointsProvider')]
    public function testRequiresAuthentication(string $method, string $url): void
    {
        $client = static::createClient();

        $client->request($method, $url);

        self::assertResponseStatusCodeSame(401);
    }

    #[DataProvider('superAdminEndpointsProvider')]
    public function testDeniesAccessWithRoleUser(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getTestUser());
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('superAdminEndpointsProvider')]
    public function testDeniesAccessWithRoleAdmin(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getAdminUser());
        $client->request($method, $url);

        self::assertResponseStatusCodeSame(403);
    }

    #[DataProvider('superAdminEndpointsProvider')]
    public function testAllowsAccessWithRoleSuperAdmin(string $method, string $url): void
    {
        $client = static::createClient();

        $client->loginUser($this->getSuperAdminUser());
        $client->request($method, $url);

        $statusCode = $client->getResponse()->getStatusCode();

        self::assertNotContains($statusCode, [401, 403]);
    }

    public static function superAdminEndpointsProvider(): iterable
    {
        yield 'users index' => ['GET', '/super-admin/users'];
        yield 'games index' => ['GET', '/super-admin/games'];
        yield 'impersonate' => ['POST', '/super-admin/impersonate/test@test.com'];
        yield 'toggle ban' => ['POST', '/super-admin/toggle-ban/test@test.com'];
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

    private function getSuperAdminUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'superadmin@test.com']);
    }
}
