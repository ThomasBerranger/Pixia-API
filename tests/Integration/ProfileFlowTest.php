<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the profile management flows.
 * Uses real JWT authentication to properly test the stateless firewall.
 */
class ProfileFlowTest extends WebTestCase
{
    // ========== Update Profile Flow ==========

    public function testUpdateProfileReturnsNewJwtWithUpdatedData(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Register a user via API
        $email = 'profiletest-' . uniqid() . '@test.com';
        $password = 'testpassword123';
        $originalUsername = 'profiletest-' . uniqid();

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => $originalUsername,
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $userId = $user->getId()->toString();

        $newUsername = 'updated-' . uniqid();

        // Update profile
        $client->jsonRequest('PATCH', '/users', [
            'username' => $newUsername,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        // Verify new JWT is returned
        self::assertArrayHasKey('token', $response);
        self::assertNotEmpty($response['token']);

        // Verify username was updated in database
        $em->clear();
        $updatedUser = $em->getRepository(User::class)->find($userId);
        self::assertSame($newUsername, $updatedUser->getUsername());
        self::assertNotSame($originalUsername, $updatedUser->getUsername());
    }

    public function testUpdateProfileWithInvalidEmailFails(): void
    {
        $client = static::createClient();

        // Register a user via API
        $email = 'invalidemailtest-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'invalidemailtest-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->jsonRequest('PATCH', '/users', [
            'email' => 'not-valid-email',
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

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

        // Register a user via API
        $email = 'metest-' . uniqid() . '@test.com';
        $password = 'testpassword123';
        $username = 'metest-' . uniqid();

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => $username,
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->request('GET', '/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('user', $response);
        self::assertSame($username, $response['user']['username']);
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
}
