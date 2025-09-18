<?php

namespace App\Service;

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
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private string              $openaiApiKey,
        private PromptService       $promptService
    )
    {
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function requestGameContent(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => $this->generateHeader(),
            'json' => $this->generateJson($prompt)
        ]);

        return $response->toArray()['choices'][0]['message']['content'] ?? '';
    }

    private function generateHeader(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->openaiApiKey,
            'Content-Type' => 'application/json'
        ];
    }

    private function generateJson(string $prompt): array
    {
        return [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $this->promptService->formatPromptRequest($prompt)]
            ],
            'max_tokens' => 500
        ];
    }
}
