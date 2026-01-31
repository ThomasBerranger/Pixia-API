<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the profile management flows.
 */
class ProfileFlowTest extends WebTestCase
{
    // ========== Update Profile Flow ==========

    public function testUpdateProfileReturnsNewJwtWithUpdatedData(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = $this->createTestUser($em);
        $originalUsername = $user->getUsername();

        $client->loginUser($user);

        $newUsername = 'updated-' . uniqid();

        // Update profile
        $client->jsonRequest('PATCH', '/users', [
            'username' => $newUsername,
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        // Verify new JWT is returned
        self::assertArrayHasKey('token', $response);
        self::assertNotEmpty($response['token']);

        // Verify username was updated in database
        $em->clear();
        $updatedUser = $em->getRepository(User::class)->find($user->getId());
        self::assertSame($newUsername, $updatedUser->getUsername());
        self::assertNotSame($originalUsername, $updatedUser->getUsername());
    }

    public function testUpdateProfileWithInvalidEmailFails(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = $this->createTestUser($em);
        $client->loginUser($user);

        $client->jsonRequest('PATCH', '/users', [
            'email' => 'not-valid-email',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateProfileRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->jsonRequest('PATCH', '/users', [
            'username' => 'newusername',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ========== Get Profile (Me) Flow ==========

    public function testGetMeReturnsUserData(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $user = $this->createTestUser($em);
        $client->loginUser($user);

        $client->request('GET', '/me');

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('user', $response);
        self::assertSame($user->getUsername(), $response['user']['username']);
    }

    public function testGetMeRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->request('GET', '/me');

        self::assertResponseStatusCodeSame(401);
    }

    // ========== View Other User Profile Flow ==========

    public function testViewOtherUserProfile(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Get an existing user (from fixtures)
        $targetUser = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);

        // View profile without authentication
        $client->request('GET', '/users/' . $targetUser->getSlug());

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('user', $response);
        self::assertSame($targetUser->getUsername(), $response['user']['username']);
    }

    // ========== Helper Methods ==========

    private function createTestUser($em): User
    {
        $uniqueId = uniqid();
        $user = new User();
        $user->setEmail("testuser-{$uniqueId}@test.com")
            ->setUsername("testuser-{$uniqueId}")
            ->setPassword('hashed');

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
