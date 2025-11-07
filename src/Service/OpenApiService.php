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
            'json' => $this->generatePromptJson($prompt)
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

    private function generatePromptJson(string $prompt): array
    {
        return [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => $this->promptService->formatPromptRequest($prompt)]
            ],
            'max_tokens' => 1000
        ];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function requestGameImage(string $prompt): string
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
            'headers' => $this->generateHeader(),
            'json' => $this->generateImageJson($prompt)
        ]);

        return $response->toArray()['data'][0]['url'] ?? '';
    }

    private function generateImageJson(string $prompt): array
    {
        return [
            "model" => "dall-e-3",
            "prompt" => $prompt,
            "n" => 1,
            "size" => "1792x1024",
            "quality" => "standard",
            "style" => "vivid"
        ];
    }
}
