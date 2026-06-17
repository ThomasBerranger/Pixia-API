<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
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

    public function testCannotApproveSecondGameOnAnAlreadyApprovedDate(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getSuperAdminUser());

        $approved = $this->getApprovedTodayGame();
        $pending = $this->getPendingGame();

        $client->jsonRequest('PATCH', '/admin/games/' . $pending->getId()->toString(), [
            'status' => GameStatus::APPROVED->value,
            'date' => $approved->getDate()->format('Y-m-d'),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCanApproveGameOnAFreeDate(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getSuperAdminUser());

        $pending = $this->getPendingGame();

        $client->jsonRequest('PATCH', '/admin/games/' . $pending->getId()->toString(), [
            'status' => GameStatus::APPROVED->value,
        ]);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function adminEndpointsProvider(): iterable
    {
        yield 'games index' => ['GET', '/admin/games?month=2026-01-01'];
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

    private function getApprovedTodayGame(): Game
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy(['date' => new \DateTimeImmutable('today'), 'status' => GameStatus::APPROVED]);
    }

    private function getPendingGame(): Game
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy(['status' => GameStatus::PENDING]);
    }
}
