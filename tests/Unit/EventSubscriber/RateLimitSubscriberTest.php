<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RateLimitSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests for RateLimitSubscriber.
 *
 * Note: RateLimiterFactory is a final class and cannot be mocked.
 * These tests focus on the static configuration and event subscription.
 * Full rate limiting behavior is tested via integration/functional tests.
 */
class RateLimitSubscriberTest extends TestCase
{
    // ========== Tests getSubscribedEvents() ==========

    public function testGetSubscribedEventsReturnsControllerEvent(): void
    {
        $events = RateLimitSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::CONTROLLER, $events);
    }

    public function testSubscribedEventHasCorrectPriority(): void
    {
        $events = RateLimitSubscriber::getSubscribedEvents();

        $this->assertSame(['onKernelController', 10], $events[KernelEvents::CONTROLLER]);
    }

    public function testEventPriorityIsHigherThanDefault(): void
    {
        $events = RateLimitSubscriber::getSubscribedEvents();
        [$method, $priority] = $events[KernelEvents::CONTROLLER];

        // Priority 10 means it runs before default (0) priority listeners
        $this->assertGreaterThan(0, $priority);
    }

    public function testSubscribedMethodExists(): void
    {
        $events = RateLimitSubscriber::getSubscribedEvents();
        [$method] = $events[KernelEvents::CONTROLLER];

        $this->assertTrue(
            method_exists(RateLimitSubscriber::class, $method),
            "Method {$method} should exist in RateLimitSubscriber"
        );
    }

    // ========== Tests configuration constants ==========

    public function testLimiterConfigurationConstants(): void
    {
        // Use reflection to access private constants
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);

        $this->assertArrayHasKey('LIMITERS_CONFIG', $constants);

        $config = $constants['LIMITERS_CONFIG'];

        // Verify all expected limiters are configured
        $this->assertArrayHasKey('global_api', $config);
        $this->assertArrayHasKey('game_answer', $config);
        $this->assertArrayHasKey('auth_form', $config);
    }

    public function testGlobalApiLimiterConfiguration(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);
        $config = $constants['LIMITERS_CONFIG']['global_api'];

        $this->assertArrayHasKey('factory', $config);
        $this->assertArrayHasKey('message', $config);
        $this->assertArrayHasKey('useAuthIdentifier', $config);

        // Global API should use authenticated user identifier
        $this->assertTrue($config['useAuthIdentifier']);
    }

    public function testGameAnswerLimiterConfiguration(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);
        $config = $constants['LIMITERS_CONFIG']['game_answer'];

        $this->assertArrayHasKey('factory', $config);
        $this->assertArrayHasKey('message', $config);
        $this->assertArrayHasKey('identifierSuffix', $config);

        // Game answer should use 'id' as identifier suffix (game ID)
        $this->assertSame('id', $config['identifierSuffix']);
    }

    public function testAuthFormLimiterConfiguration(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);
        $config = $constants['LIMITERS_CONFIG']['auth_form'];

        $this->assertArrayHasKey('factory', $config);
        $this->assertArrayHasKey('message', $config);

        // Auth form should NOT use authenticated identifier (since user isn't logged in yet)
        $this->assertFalse($config['useAuthIdentifier']);
    }

    public function testAllLimitersHaveErrorMessages(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);

        foreach ($constants['LIMITERS_CONFIG'] as $name => $config) {
            $this->assertArrayHasKey('message', $config, "Limiter {$name} should have a message");
            $this->assertNotEmpty($config['message'], "Limiter {$name} message should not be empty");
        }
    }

    public function testLimiterMessagesAreUserFriendly(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constants = $reflection->getConstants(\ReflectionClassConstant::IS_PRIVATE);

        foreach ($constants['LIMITERS_CONFIG'] as $name => $config) {
            $message = $config['message'];

            // Messages should not contain technical terms
            $this->assertStringNotContainsString('rate limit', strtolower($message));
            $this->assertStringNotContainsString('exception', strtolower($message));
            $this->assertStringNotContainsString('error', strtolower($message));
        }
    }

    // ========== Tests class structure ==========

    public function testClassIsReadonly(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);

        $this->assertTrue($reflection->isReadOnly());
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);

        $this->assertTrue(
            $reflection->implementsInterface(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)
        );
    }

    public function testConstructorAcceptsRequiredDependencies(): void
    {
        $reflection = new \ReflectionClass(RateLimitSubscriber::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);

        $params = $constructor->getParameters();
        $this->assertCount(5, $params);

        // Verify parameter names
        $paramNames = array_map(fn($p) => $p->getName(), $params);
        $this->assertContains('globalApiLimiter', $paramNames);
        $this->assertContains('gameAnswerLimiter', $paramNames);
        $this->assertContains('authFormLimiter', $paramNames);
        $this->assertContains('tokenStorage', $paramNames);
        $this->assertContains('environment', $paramNames);
    }
}
