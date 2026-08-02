<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\AppCompatibility;
use App\EventSubscriber\AppVersionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AppVersionSubscriberTest extends TestCase
{
    private function requestEvent(?string $appVersion, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $request = new Request();

        if ($appVersion !== null) {
            $request->headers->set(AppVersionSubscriber::APP_VERSION_HEADER, $appVersion);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, $requestType);
    }

    public function testOutdatedVersionIsRejectedWith426(): void
    {
        $event = $this->requestEvent('3.7.0');

        (new AppVersionSubscriber())->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(426, $response->getStatusCode());
    }

    public function testMinimalVersionPasses(): void
    {
        $event = $this->requestEvent(AppCompatibility::MIN_APP_VERSION);

        (new AppVersionSubscriber())->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testNewerVersionPasses(): void
    {
        $event = $this->requestEvent('99.0.0');

        (new AppVersionSubscriber())->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testMissingHeaderPasses(): void
    {
        $event = $this->requestEvent(null);

        (new AppVersionSubscriber())->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }

    public function testMalformedHeaderPasses(): void
    {
        foreach (['garbage', '', '3.8', 'v3.8.0', '3.8.0-beta'] as $malformed) {
            $event = $this->requestEvent($malformed);

            (new AppVersionSubscriber())->onKernelRequest($event);

            $this->assertNull($event->getResponse(), "Header malformé '{$malformed}' ne devrait pas bloquer");
        }
    }

    public function testSubRequestIsIgnored(): void
    {
        $event = $this->requestEvent('1.0.0', HttpKernelInterface::SUB_REQUEST);

        (new AppVersionSubscriber())->onKernelRequest($event);

        $this->assertNull($event->getResponse());
    }
}
