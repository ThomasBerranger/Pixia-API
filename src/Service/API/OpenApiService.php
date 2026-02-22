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
        #[Autowire('%env(MULTIVERS_PROMPT_INSTRUCTION)%')]
        private string $promptInstruction,
    ) {
    }

    /**
     * @return string[]
     *
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
            'json' => $this->generatePromptJson($prompt),
        ]);

        /** @var array{choices: array<int, array{finish_reason: string, message: array{content?: string, refusal?: string}}>} $data */
        $data = $response->toArray();

        if (!isset($data['choices'][0]['finish_reason'])) {
            throw new \RuntimeException('Réponse OpenAI invalide');
        }

        if ('length' === $data['choices'][0]['finish_reason']) {
            throw new \RuntimeException('Réponse tronquée : augmentez max_tokens');
        }

        if (isset($data['choices'][0]['message']['refusal'])) {
            throw new \RuntimeException('Requête refusée par OpenAI : '.(string) $data['choices'][0]['message']['refusal']);
        }

        $content = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($content)) {
            throw new \RuntimeException('Contenu de la réponse invalide');
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException('JSON invalide dans la réponse');
        }

        /** @var array<string> */
        return $decoded;
    }

    /**
     * @return string[]
     */
    private function generateHeader(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->openaiApiKey,
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return array<string, array<int|string, array<string, array<string, array<int|string, array<string, string>|string>|bool|string>|bool|string>|string>|int|string>
     */
    private function generatePromptJson(string $prompt): array
    {
        return [
            'model' => 'gpt-4o-2024-08-06',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->promptInstruction,
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
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
                                'description' => 'Prompt DALL-E ultra-détaillé avec noms des personnages et univers',
                            ],
                            'solution' => [
                                'type' => 'string',
                                'description' => 'Nom FRANCAIS des 2 sources séparées par le mot "et" et ne finit pas par un . (ex: Intouchables et Léon). Doit obligatoirement faire entre 5 et 255 caractères',
                            ],
                            'englishSolution' => [
                                'type' => 'string',
                                'description' => 'Nom ANGLAIS des 2 sources séparées par le mot "and" et ne finit pas par un . (ex: Harry Potter and Star Wars). Doit obligatoirement faire entre 5 et 255 caractères',
                            ],
                            'hint' => [
                                'type' => 'string',
                                'description' => 'Indice subtil sans révéler les noms. Doit obligatoirement faire entre 5 et 255 caractères',
                            ],
                        ],
                        'required' => ['prompt', 'solution', 'englishSolution', 'hint'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];
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
        $this->logger->notice('=== OPENAI IMAGE REQUEST ===', [
            'prompt' => $prompt,
        ]);

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
            'headers' => $this->generateHeader(),
            'json' => $this->generateImageJson($prompt),
        ]);

        /** @var array{data: array<int, array{url: string}>} $data */
        $data = $response->toArray();
        $url = $data['data'][0]['url'] ?? null;

        if (!is_string($url)) {
            $this->logger->error('OpenAI image URL not found', ['response' => $data]);
            throw new \RuntimeException('URL d\'image non trouvée dans la réponse');
        }

        $this->logger->notice('OpenAI image generated', ['url' => $url]);
        $this->logger->notice('=== END OPENAI IMAGE ===');

        return $url;
    }

    /**
     * @return array<string, int|string>
     */
    private function generateImageJson(string $prompt): array
    {
        return [
            'model' => 'dall-e-3',
            'prompt' => $prompt,
            'n' => 1,
            'size' => '1792x1024',
            'quality' => 'standard',
            'style' => 'vivid',
        ];
    }
}
