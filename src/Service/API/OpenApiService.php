<?php

declare(strict_types=1);

namespace App\Service\API;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class OpenApiService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private string $openaiApiKey,
    ) {
    }

    /**
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function requestGameImage(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
            'headers' => [
                'Authorization' => 'Bearer '.$this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'dall-e-3',
                'prompt' => $prompt,
                'n' => 1,
                'size' => '1792x1024',
                'quality' => 'standard',
                'style' => 'vivid',
            ],
        ]);

        /** @var array{data: array<int, array{url: string}>} $data */
        $data = $response->toArray();
        $url = $data['data'][0]['url'] ?? null;

        if (!is_string($url)) {
            $this->logger->error('OpenAI image URL not found', ['response' => $data]);
            throw new \RuntimeException('URL d\'image non trouvée dans la réponse');
        }

        return $url;
    }
}
