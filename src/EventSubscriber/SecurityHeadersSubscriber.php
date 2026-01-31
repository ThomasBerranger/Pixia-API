<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Adds security headers to all responses.
 * These headers help protect against common web vulnerabilities.
 */
readonly class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Enable XSS filter in browsers (legacy, but still useful)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrict permissions/features
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        // Remove PHP version exposure
        $response->headers->remove('X-Powered-By');

        // Content Security Policy for API (restrictive since we only serve JSON)
        $response->headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }
}
