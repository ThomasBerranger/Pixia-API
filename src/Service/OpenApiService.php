<?php

namespace App\Service;

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
        #[Autowire('%env(OPENAI_API_KEY)%')]
        private string              $openaiApiKey,
        #[Autowire('%env(MULTIVERS_PROMPT_INSTRUCTION)%')]
        private string              $promptInstruction,
        private LoggerInterface     $logger
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
    public function requestGameContent(string $prompt): array
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => $this->generateHeader(),
            'json' => $this->generatePromptJson($prompt)
        ]);

        $data = $response->toArray();

        if ($data['choices'][0]['finish_reason'] === 'length') {
            throw new \RuntimeException('Réponse tronquée : augmentez max_tokens');
        }

        if (isset($data['choices'][0]['message']['refusal'])) {
            throw new \RuntimeException('Requête refusée par OpenAI : ' . $data['choices'][0]['message']['refusal']);
        }

        return json_decode($data['choices'][0]['message']['content'], true);
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
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->promptInstruction
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => [
                    'name' => 'game_prompt_response',
                    'strict' => true,
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'prompt' => [
                                'type' => 'string',
                                'description' => 'Prompt DALL-E ultra-détaillé avec noms des personnages et univers'
                            ],
                            'solution' => [
                                'type' => 'string',
                                'description' => 'Nom des 2 sources séparées par le mot "et" et ne finit pas par un . (ex: Harry Potter et Star Wars)'
                            ],
                            'hint' => [
                                'type' => 'string',
                                'description' => 'Indice subtil sans révéler les noms'
                            ]
                        ],
                        'required' => ['prompt', 'solution', 'hint'],
                        'additionalProperties' => false
                    ]
                ]
            ]
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
