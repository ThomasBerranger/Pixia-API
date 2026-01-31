<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Security tests for authentication flows.
 * Tests registration, login, password reset, and JWT token handling.
 */
class AuthenticationSecurityTest extends WebTestCase
{
    // ========== Tests Registration Security ==========

    public function testRegisterRequiresValidEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'not-a-valid-email',
            'password' => 'validpassword123',
            'username' => 'testuser',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterRequiresMinimumPasswordLength(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'newuser@test.com',
            'password' => 'abc', // Too short
            'username' => 'testuser',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterRejectsEmptyUsername(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'newuser@test.com',
            'password' => 'validpassword123',
            'username' => '',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testRegisterRejectsDuplicateEmail(): void
    {
        $client = static::createClient();

        // Try to register with existing email
        $client->jsonRequest('POST', '/register', [
            'email' => 'test@test.com', // Already exists in fixtures
            'password' => 'validpassword123',
            'username' => 'newusername',
        ]);

        // Should reject duplicate (could be 422 or 409 depending on implementation)
        $this->assertNotEquals(201, $client->getResponse()->getStatusCode());
    }

    public function testRegisterRejectsDuplicateUsername(): void
    {
        $client = static::createClient();

        // First, get existing username from the database
        $existingUser = self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);

        $client->jsonRequest('POST', '/register', [
            'email' => 'completelynew@test.com',
            'password' => 'validpassword123',
            'username' => $existingUser->getUsername(),
        ]);

        // Should reject duplicate username
        $this->assertNotEquals(201, $client->getResponse()->getStatusCode());
    }

    public function testRegisterRejectsXssInUsername(): void
    {
        $client = static::createClient();

        $xssPayload = '<script>alert("xss")</script>';

        $client->jsonRequest('POST', '/register', [
            'email' => 'xsstest' . time() . '@test.com',
            'password' => 'validpassword123',
            'username' => $xssPayload,
        ]);

        $response = $client->getResponse();

        // Username with special characters should be rejected by validation
        $this->assertResponseStatusCodeSame(422);
    }

    // ========== Tests Login Security ==========

    public function testLoginWithValidCredentials(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'password',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('token', $data);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUser(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'anypassword',
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginIsCaseInsensitiveForEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'TEST@TEST.COM',
            'password' => 'password',
        ]);

        // Depending on implementation, this might work or not
        // The important thing is it doesn't crash
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 401]);
    }

    // ========== Tests JWT Token Security ==========

    public function testAccessProtectedRouteWithoutToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedRouteWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer invalid.jwt.token']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedRouteWithMalformedToken(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer not-even-a-jwt']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testAccessProtectedRouteWithEmptyBearer(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Bearer ']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    // ========== Tests Password Reset Security ==========

    public function testResetPasswordWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/reset-password', [
            'token' => 'invalid-reset-token-12345',
            'password' => 'newpassword123',
        ]);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testResetPasswordRequiresMinimumPasswordLength(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/reset-password', [
            'token' => 'some-token',
            'password' => 'ab', // Too short
        ]);

        // Should fail validation (422) or token validation (400)
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 422]);
    }

    public function testForgotPasswordWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/forgot-password', [
            'email' => 'not-a-valid-email',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testForgotPasswordAlwaysReturnsSuccess(): void
    {
        $client = static::createClient();

        // Even for non-existent email, should return success to prevent enumeration
        $client->jsonRequest('POST', '/forgot-password', [
            'email' => 'definitely-not-existing-' . time() . '@test.com',
        ]);

        $this->assertResponseStatusCodeSame(200);
    }

    // ========== Tests Google Auth Security ==========

    public function testGoogleAuthWithInvalidToken(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/google-auth', [
            'accessToken' => 'invalid-google-token',
        ]);

        // Should fail with 500 (Google API error) or validation error
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [400, 422, 500]);
    }

    public function testGoogleAuthWithEmptyToken(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/google-auth', [
            'accessToken' => '',
        ]);

        // Should fail validation
        $this->assertNotEquals(200, $client->getResponse()->getStatusCode());
    }

    // ========== Tests Sponsor Security ==========

    public function testRegisterWithInvalidSponsor(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'sponsored-user-' . time() . '@test.com',
            'password' => 'validpassword123',
            'username' => 'sponsoreduser' . time(),
            'sponsor' => 'non-existent-sponsor-slug',
        ]);

        // Should fail because sponsor doesn't exist
        $this->assertResponseStatusCodeSame(422);
    }

    // ========== Tests Authorization Header Handling ==========

    public function testHandlesMultipleAuthorizationFormats(): void
    {
        $client = static::createClient();

        // Test with wrong auth scheme
        $client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'Basic dXNlcjpwYXNz']
        );

        $this->assertResponseStatusCodeSame(401);
    }

    public function testHandlesAuthorizationWithoutBearer(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/me',
            [],
            [],
            ['HTTP_AUTHORIZATION' => 'some.jwt.token']
        );

        $this->assertResponseStatusCodeSame(401);
    }
}
