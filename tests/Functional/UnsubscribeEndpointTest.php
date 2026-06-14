<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class UnsubscribeEndpointTest extends WebTestCase
{
    private function userRepository(KernelBrowser $client): UserRepository
    {
        return $client->getContainer()->get(UserRepository::class);
    }

    public function testValidSignedLinkUnsubscribesAndRedirects(): void
    {
        $client = static::createClient();
        $container = $client->getContainer();

        $user = $this->userRepository($client)->findOneBy(['email' => 'test@test.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->hasEmailConsent());

        $url = $container->get('router')->generate(
            'unsubscribe',
            ['id' => (string) $user->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );
        $signedUrl = $container->get(UriSigner::class)->sign($url);

        $client->request('GET', $signedUrl);

        self::assertResponseRedirects('http://localhost/?unsubscribed', 302);

        $refreshed = $this->userRepository($client)->findOneBy(['email' => 'test@test.com']);
        self::assertInstanceOf(User::class, $refreshed);
        self::assertFalse($refreshed->hasEmailConsent());
    }

    public function testInvalidSignatureReturnsBadRequest(): void
    {
        $client = static::createClient();

        $user = $this->userRepository($client)->findOneBy(['email' => 'test@test.com']);
        self::assertInstanceOf(User::class, $user);

        // No _hash → signature check fails.
        $client->request('GET', '/unsubscribe?id=' . $user->getId());

        self::assertResponseStatusCodeSame(400);
        self::assertTrue($user->hasEmailConsent(), 'Consent must not change on an invalid link');
    }
}
