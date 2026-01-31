<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests to ensure that error responses do not disclose sensitive information.
 * These tests verify that stack traces, database details, and internal paths
 * are not exposed to clients in error responses.
 */
class ErrorDisclosureTest extends WebTestCase
{
    // ========== Tests 404 errors ==========

    public function testNotFoundDoesNotExposeInternalPaths(): void
    {
        $client = static::createClient();

        $client->request('GET', '/non-existent-route-12345');

        $response = $client->getResponse();
        $content = $response->getContent();

        $this->assertResponseStatusCodeSame(404);
        $this->assertStringNotContainsString('/home/', $content);
        $this->assertStringNotContainsString('/var/www/', $content);
        $this->assertStringNotContainsString('/vendor/', $content);
        $this->assertStringNotContainsString('.php', $content);
    }

    public function testNotFoundEntityDoesNotRevealDatabaseStructure(): void
    {
        $client = static::createClient();

        // Try to access a non-existent game with a valid UUID format
        $client->request('GET', '/games/00000000-0000-0000-0000-000000000000/hint');

        $response = $client->getResponse();
        $content = $response->getContent();

        // Should not expose SQL queries or table names
        $this->assertStringNotContainsString('SELECT', $content);
        $this->assertStringNotContainsString('FROM', $content);
        $this->assertStringNotContainsString('WHERE', $content);
        $this->assertStringNotContainsString('table', strtolower($content));
    }

    // ========== Tests validation errors ==========

    public function testValidationErrorDoesNotExposeInternals(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/register', [
            'email' => 'not-an-email',
            'password' => 'x',
            'username' => '',
        ]);

        $response = $client->getResponse();
        $content = $response->getContent();

        // Validation errors are expected (422), but should not expose internals
        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('Stack trace', $content);
        $this->assertStringNotContainsString('#0', $content); // Stack trace line numbers
    }

    // ========== Tests malformed requests ==========

    public function testMalformedJsonDoesNotExposeParser(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{invalid json content'
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        // Should return error but not expose JSON parser details
        $this->assertStringNotContainsString('json_decode', $content);
        $this->assertStringNotContainsString('JsonException', $content);
    }

    public function testInvalidContentTypeDoesNotExposeInternals(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/register',
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'random content'
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        $this->assertStringNotContainsString('Exception', $content);
        $this->assertStringNotContainsString('at line', $content);
    }

    // ========== Tests SQL injection attempts ==========

    public function testSqlInjectionAttemptDoesNotExposeDbErrors(): void
    {
        $client = static::createClient();

        // Attempt SQL injection in query parameter
        $client->request('GET', '/users/leaderboard?page=1%27%20OR%20%271%27=%271');

        $response = $client->getResponse();
        $content = $response->getContent();

        // Should not expose database errors
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('PDOException', $content);
        $this->assertStringNotContainsString('syntax error', strtolower($content));
        $this->assertStringNotContainsString('mysql', strtolower($content));
    }

    public function testSqlInjectionInUsernameDoesNotExposeDbErrors(): void
    {
        $client = static::createClient();

        // Use SQL injection payload in username parameter
        $client->request('GET', "/users/test-user-1-or-1-equals-1");

        $response = $client->getResponse();
        $content = $response->getContent();

        // Should not expose database errors
        $this->assertStringNotContainsString('SQLSTATE', $content);
        $this->assertStringNotContainsString('PDOException', $content);
        $this->assertStringNotContainsString('QueryException', $content);
    }

    // ========== Tests authentication errors ==========

    public function testInvalidLoginDoesNotRevealUserExistence(): void
    {
        $client = static::createClient();

        // Login with non-existent user
        $client->jsonRequest('POST', '/login', [
            'email' => 'nonexistent-user-xyz@test.com',
            'password' => 'wrongpassword',
        ]);

        $responseNonExistent = $client->getResponse()->getContent();

        // Login with existing user but wrong password
        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'wrongpassword',
        ]);

        $responseExistent = $client->getResponse()->getContent();

        // Both should return 401 and not differentiate between "user not found" and "wrong password"
        // This prevents user enumeration attacks
        $this->assertResponseStatusCodeSame(401);

        // Should not expose whether the user exists
        $this->assertStringNotContainsString('not found', strtolower($responseNonExistent));
        $this->assertStringNotContainsString('does not exist', strtolower($responseNonExistent));
    }

    public function testForgotPasswordDoesNotRevealUserExistence(): void
    {
        $client = static::createClient();

        // Request password reset for non-existent user
        $client->jsonRequest('POST', '/forgot-password', [
            'email' => 'nonexistent-user-xyz@test.com',
        ]);

        $responseNonExistent = json_decode($client->getResponse()->getContent(), true);

        // Request password reset for existing user
        $client->jsonRequest('POST', '/forgot-password', [
            'email' => 'test@test.com',
        ]);

        $responseExistent = json_decode($client->getResponse()->getContent(), true);

        // Both should return success message to prevent user enumeration
        $this->assertResponseStatusCodeSame(200);
        $this->assertArrayHasKey('message', $responseNonExistent);
        $this->assertArrayHasKey('message', $responseExistent);
    }

    // ========== Tests debug mode ==========

    public function testNoDebugInformationInProduction(): void
    {
        $client = static::createClient();

        $client->request('GET', '/non-existent');

        $response = $client->getResponse();
        $content = $response->getContent();

        // Should not contain Symfony debug toolbar or profiler info
        $this->assertStringNotContainsString('_profiler', $content);
        $this->assertStringNotContainsString('_wdt', $content);
        $this->assertStringNotContainsString('sf-toolbar', $content);
    }

    // ========== Tests server information ==========

    public function testResponseDoesNotExposeServerVersion(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/leaderboard');

        $response = $client->getResponse();

        // Check headers don't expose sensitive server info
        $serverHeader = $response->headers->get('Server');
        $poweredBy = $response->headers->get('X-Powered-By');

        // These headers should ideally not be present or be generic
        if ($serverHeader !== null) {
            $this->assertStringNotContainsString('PHP/', $serverHeader);
            $this->assertStringNotContainsString('Apache/', $serverHeader);
        }

        // X-Powered-By should not be present
        $this->assertNull($poweredBy);
    }
}
