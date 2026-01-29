<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    private SecurityHeadersSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new SecurityHeadersSubscriber();
    }

    // ========== Tests getSubscribedEvents() ==========

    public function testGetSubscribedEventsReturnsResponseEvent(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
    }

    // ========== Tests security headers are set ==========

    public function testSetsXContentTypeOptionsHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('nosniff', $event->getResponse()->headers->get('X-Content-Type-Options'));
    }

    public function testSetsXFrameOptionsHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('DENY', $event->getResponse()->headers->get('X-Frame-Options'));
    }

    public function testSetsXXssProtectionHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('1; mode=block', $event->getResponse()->headers->get('X-XSS-Protection'));
    }

    public function testSetsReferrerPolicyHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('strict-origin-when-cross-origin', $event->getResponse()->headers->get('Referrer-Policy'));
    }

    public function testSetsPermissionsPolicyHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame(
            'geolocation=(), microphone=(), camera=()',
            $event->getResponse()->headers->get('Permissions-Policy')
        );
    }

    public function testSetsContentSecurityPolicyHeader(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $this->assertSame(
            "default-src 'none'; frame-ancestors 'none'",
            $event->getResponse()->headers->get('Content-Security-Policy')
        );
    }

    public function testRemovesXPoweredByHeader(): void
    {
        $event = $this->createResponseEvent();
        $event->getResponse()->headers->set('X-Powered-By', 'PHP/8.4');

        $this->subscriber->onKernelResponse($event);

        $this->assertNull($event->getResponse()->headers->get('X-Powered-By'));
    }

    // ========== Tests sub-request handling ==========

    public function testSkipsSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        $event = new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::SUB_REQUEST,
            $response
        );

        $this->subscriber->onKernelResponse($event);

        // Headers should NOT be set for sub-requests
        $this->assertNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('X-Frame-Options'));
    }

    // ========== Tests headers don't override existing ==========

    public function testSetsAllHeadersOnMainRequest(): void
    {
        $event = $this->createResponseEvent();

        $this->subscriber->onKernelResponse($event);

        $headers = $event->getResponse()->headers;

        // Verify all security headers are present
        $this->assertNotNull($headers->get('X-Content-Type-Options'));
        $this->assertNotNull($headers->get('X-Frame-Options'));
        $this->assertNotNull($headers->get('X-XSS-Protection'));
        $this->assertNotNull($headers->get('Referrer-Policy'));
        $this->assertNotNull($headers->get('Permissions-Policy'));
        $this->assertNotNull($headers->get('Content-Security-Policy'));
    }

    // ========== Helper methods ==========

    private function createResponseEvent(): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();

        return new ResponseEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }
}
