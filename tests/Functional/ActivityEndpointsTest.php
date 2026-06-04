<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Activity;
use App\Entity\User;
use App\Enum\ActivityType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ActivityEndpointsTest extends WebTestCase
{
    // ========== Matrice d'accès ==========

    public function testRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/activities');

        self::assertResponseStatusCodeSame(401);
    }

    public function testDeniesAccessWithRoleUser(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByEmail('test@test.com'));
        $client->request('GET', '/activities');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeniesAccessWithRoleAdmin(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByEmail('admin@test.com'));
        $client->request('GET', '/activities');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAllowsAccessWithRoleSuperAdmin(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->request('GET', '/activities');

        self::assertResponseIsSuccessful();
    }

    // ========== Format de réponse ==========

    public function testResponseExposesPaginationEnvelope(): void
    {
        $client = static::createClient();
        $player = $this->getUserByEmail('test@test.com');
        $this->createActivity($player, ActivityType::PLAYER_TOKENS_CLAIM, ['addedTokens' => 40]);

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->jsonRequest('GET', '/activities');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('items', $data);
        self::assertArrayHasKey('total', $data);
        self::assertArrayHasKey('page', $data);
        self::assertArrayHasKey('perPage', $data);
        self::assertSame(1, $data['page']);
    }

    public function testItemExposesExpectedFields(): void
    {
        $client = static::createClient();
        $player = $this->getUserByEmail('test@test.com');
        $this->createActivity($player, ActivityType::PLAYER_TOKENS_CLAIM, ['addedTokens' => 40]);

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->jsonRequest('GET', '/activities');

        $data = json_decode($client->getResponse()->getContent(), true);
        $item = $data['items'][0];

        self::assertEqualsCanonicalizing(['player', 'type', 'data', 'createdAt'], array_keys($item));
        self::assertSame(['username'], array_keys($item['player']));
        self::assertArrayNotHasKey('email', $item['player']);
    }

    // ========== Recherche par username ==========

    public function testSearchFiltersByUsername(): void
    {
        $client = static::createClient();
        $testUser = $this->getUserByEmail('test@test.com');
        $adminUser = $this->getUserByEmail('admin@test.com');

        $this->createActivity($testUser, ActivityType::PLAYER_TOKENS_CLAIM);
        $this->createActivity($adminUser, ActivityType::ADMIN_TOKENS_CLAIM);

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->jsonRequest('GET', '/activities?search=admin-user');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('admin-user', $data['items'][0]['player']['username']);
    }

    public function testSearchWithoutMatchReturnsEmpty(): void
    {
        $client = static::createClient();
        $this->createActivity($this->getUserByEmail('test@test.com'), ActivityType::PLAYER_TOKENS_CLAIM);

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->jsonRequest('GET', '/activities?search=does-not-exist');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(0, $data['total']);
        self::assertCount(0, $data['items']);
    }

    // ========== Validation ==========

    public function testNonPositivePageIsRejected(): void
    {
        $client = static::createClient();

        $client->loginUser($this->getUserByEmail('superadmin@test.com'));
        $client->jsonRequest('GET', '/activities?page=0');

        self::assertResponseStatusCodeSame(422);
    }

    // ========== Helpers ==========

    private function createActivity(User $player, ActivityType $type, ?array $data = null): void
    {
        $em = $this->getEntityManager();
        $em->persist(new Activity($player, $type, $data));
        $em->flush();
    }

    private function getUserByEmail(string $email): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }
}
