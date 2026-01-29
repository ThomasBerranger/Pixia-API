<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests to verify security headers are present in all responses.
 */
class SecurityHeadersTest extends WebTestCase
{
    public function testPublicEndpointHasSecurityHeaders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/leaderboard');

        $response = $client->getResponse();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
    }

    public function testErrorResponseHasSecurityHeaders(): void
    {
        $client = static::createClient();

        $client->request('GET', '/non-existent-route');

        $response = $client->getResponse();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testAuthEndpointHasSecurityHeaders(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response = $client->getResponse();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
    }

    public function testXPoweredByHeaderIsNotPresent(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/leaderboard');

        $response = $client->getResponse();

        $this->assertNull($response->headers->get('X-Powered-By'));
    }

    public function testContentSecurityPolicyIsRestrictive(): void
    {
        $client = static::createClient();

        $client->request('GET', '/users/leaderboard');

        $csp = $client->getResponse()->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }
}
