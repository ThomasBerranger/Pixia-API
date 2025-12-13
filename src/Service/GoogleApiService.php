<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class GoogleApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
    )
    {
    }

    /**
     * @return array{email: string, name: string}
     * @throws ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface
     */
    public function authenticate(string $accessToken): array
    {
        $response = $this->httpClient->request('GET',
            'https://www.googleapis.com/oauth2/v3/userinfo', [
                'headers' => ['Authorization' => 'Bearer ' . $accessToken],
            ]);

        $userInfo = $response->toArray();

        assert(isset($userInfo['email']) && is_string($userInfo['email']));
        assert(isset($userInfo['name']) && is_string($userInfo['name']));

        return [
            'email' => $userInfo['email'],
            'name' => $userInfo['name'],
        ];
    }
}
