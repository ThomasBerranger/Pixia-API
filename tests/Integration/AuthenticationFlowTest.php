<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the complete authentication flows.
 */
class AuthenticationFlowTest extends WebTestCase
{
    // ========== Register Flow ==========

    public function testRegisterCreatesUserInDatabase(): void
    {
        $client = static::createClient();
        $email = 'newuser-' . uniqid() . '@test.com';
        $username = 'newuser-' . uniqid();

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'password123',
            'username' => $username,
        ]);

        self::assertResponseStatusCodeSame(201);
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $response);

        // Verify user exists in database
        $user = self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertNotNull($user);
        self::assertSame($username, $user->getUsername());
    }

    public function testRegisterAndLoginFlow(): void
    {
        $client = static::createClient();
        $email = 'flowuser-' . uniqid() . '@test.com';
        $password = 'password123';

        // Register
        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'flowuser-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);

        // Login with same credentials
        $client->jsonRequest('POST', '/login', [
            'email' => $email,
            'password' => $password,
        ]);

        self::assertResponseStatusCodeSame(200);
        $loginResponse = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $loginResponse);

        // Use token to access protected endpoint
        $client->request('GET', '/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $loginResponse['token'],
        ]);
        self::assertResponseIsSuccessful();
    }

    // ========== Validation Tests ==========

    public function testRegisterWithInvalidEmailFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'not-an-email',
            'password' => 'password123',
            'username' => 'testuser-' . uniqid(),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterWithShortPasswordFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'valid-' . uniqid() . '@email.com',
            'password' => 'abc',
            'username' => 'testuser-' . uniqid(),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $client = static::createClient();
        $email = 'wrongpw-' . uniqid() . '@test.com';

        // First create a user
        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'correctpassword',
            'username' => 'wrongpw-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);

        // Try login with wrong password
        $client->jsonRequest('POST', '/login', [
            'email' => $email,
            'password' => 'wrongpassword',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUserFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'nonexistent-' . uniqid() . '@test.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ========== Register with Sponsor Flow ==========

    public function testRegisterWithSponsor(): void
    {
        $client = static::createClient();

        $sponsor = self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);

        $email = 'sponsored-' . uniqid() . '@test.com';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'password123',
            'username' => 'sponsored-' . uniqid(),
            'sponsor' => $sponsor->getSlug(),
        ]);

        self::assertResponseStatusCodeSame(201);

        // Verify sponsor is set in database
        $newUser = self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertNotNull($newUser->getSponsor());
        self::assertSame($sponsor->getId()->toString(), $newUser->getSponsor()->getId()->toString());
    }
}
