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
    // ========== Register + Login Flow ==========

    public function testRegisterAndLoginFlow(): void
    {
        $client = static::createClient();
        $email = 'newuser-' . uniqid() . '@test.com';

        // Step 1: Register
        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'password123',
            'username' => 'newuser-' . uniqid(),
        ]);

        self::assertResponseStatusCodeSame(201);
        $registerResponse = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $registerResponse);

        // Step 2: Login with same credentials
        $client->jsonRequest('POST', '/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(200);
        $loginResponse = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $loginResponse);

        // Step 3: Use token to access protected endpoint
        $client->request('GET', '/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $loginResponse['token'],
        ]);

        self::assertResponseIsSuccessful();
    }

    public function testRegisterWithInvalidEmailFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'not-an-email',
            'password' => 'password123',
            'username' => 'testuser',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testRegisterWithShortPasswordFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'valid@email.com',
            'password' => 'abc',
            'username' => 'testuser',
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLoginWithWrongPasswordFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'wrongpassword',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUserFails(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'password123',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // ========== Register with Sponsor Flow ==========

    public function testRegisterWithSponsor(): void
    {
        $client = static::createClient();

        // Get sponsor's slug
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

        // Verify the new user has the sponsor set
        $newUser = self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => $email]);

        self::assertNotNull($newUser->getSponsor());
        self::assertSame($sponsor->getId()->toString(), $newUser->getSponsor()->getId()->toString());
    }
}
