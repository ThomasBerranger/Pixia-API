<?php

declare(strict_types=1);

namespace App\Service\API;

use App\DTO\API\MovieAnalysisResult;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ClaudeApiService
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';
    private const string MODEL = 'claude-haiku-4-5-20251001';
    private const float TEMPERATURE = 0;
    private const int MAX_TOKENS = 4096;
    private const int MIN_NOTORIETY_SCORE = 50;

    public function __construct(
        private HttpClientInterface $httpClient,
        #[Autowire('%env(CLAUDE_CONSOLE_KEY)%')]
        private string              $claudeConsoleKey,
        private ClaudeApiLogger     $claudeApiLogger,
    )
    {
    }

    public function analyzeMovieIdea(string $idea, ?User $user = null): MovieAnalysisResult
    {
        $this->claudeApiLogger->logRequest($user, $idea);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'timeout' => 30,
            'max_duration' => 120,
            'headers' => [
                'x-api-key' => $this->claudeConsoleKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'temperature' => self::TEMPERATURE,
                'system' => [
                    [
                        'type' => 'text',
                        'text' => $this->getSystemPrompt(),
                        'cache_control' => ['type' => 'ephemeral'],
                    ]
                ],
                'tools' => [
                    [
                        'type' => 'web_search_20250305',
                        'name' => 'web_search',
                        'max_uses' => 2,
                        'user_location' => [
                            'type' => 'approximate',
                            'country' => 'FR',
                            'timezone' => 'Europe/Paris',
                        ],
                    ]
                ],
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $idea,
                    ]
                ]
            ]
        ]);

        try {
            $data = $response->toArray();
        } catch (HttpExceptionInterface $e) {
            $body = $response->getContent(false);
            $this->claudeApiLogger->logError($e, $body);
            $this->claudeApiLogger->logEnd();

            throw new \RuntimeException(
                sprintf('Anthropic API HTTP %d: %s', $response->getStatusCode(), $body),
                $response->getStatusCode(),
                $e,
            );
        }

        $stopReason = $data['stop_reason'] ?? null;
        if ($stopReason === 'max_tokens') {
            $this->claudeApiLogger->logEnd();
            throw new \RuntimeException('La réponse Claude a été tronquée (max_tokens atteint).');
        }

        $content = '';
        $blocks = $data['content'] ?? [];
        if (!is_array($blocks)) {
            $this->claudeApiLogger->logEnd();
            throw new \RuntimeException('Réponse Anthropic invalide : clé "content" manquante ou non-itérable dans la réponse API.');
        }
        foreach ($blocks as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text = $block['text'] ?? '';
                $content .= is_string($text) ? $text : '';
            }
        }

        $this->claudeApiLogger->logRawResponse($content);

        $content = $this->extractJson($content);
        $this->claudeApiLogger->logExtractedJson($content);

        try {
            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($result)) {
                $this->claudeApiLogger->logEnd();
                throw new \RuntimeException('Réponse Anthropic invalide : le JSON décodé n\'est pas un objet. Raw: ' . $content);
            }

            /** @var array<string, mixed> $result */
            $this->claudeApiLogger->logParsedResult($result);
            $this->claudeApiLogger->logEnd();

            return MovieAnalysisResult::fromArray($result);
        } catch (\JsonException $e) {
            $this->claudeApiLogger->logError($e, $content);
            $this->claudeApiLogger->logEnd();

            throw new \RuntimeException("JSON parsing failed. Raw content: " . $content . " | Error: " . $e->getMessage());
        }
    }

    private function extractJson(string $content): string
    {
        $content = preg_replace('/^```json\s*/s', '', $content) ?? $content;
        $content = preg_replace('/\s*```$/s', '', $content) ?? $content;

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false) {
            return trim($content);
        }

        return trim(substr($content, $start, $end - $start + 1));
    }

    private function getSystemPrompt(): string
    {
        $minScore = self::MIN_NOTORIETY_SCORE;

        return <<<PROMPT
You are a French cinema expert and a visual prompt creator for a movie guessing game called Pixia.

## Mission
The user gives you 0, 1, or 2 movie titles. You must:
1. Evaluate their notoriety in France
2. If the notorietyScore is sufficient, generate an image prompt and a hint

## Handling user input
- 0 recognized movies or unrecognized request: pick 2 popular movies yourself, each with notorietyScore ≥ 75
- 1 movie cited: complete with a second movie of your choice (notorietyScore ≥ 75) that pairs well visually with the first
- 2 movies cited: normal processing
- 3+ movies cited: keep only the first 2, ignore the rest
- TV series cited: ignore and replace with a movie of your choice (notorietyScore ≥ 75)
- Same movie twice: keep it and add a second movie of your choice (notorietyScore ≥ 75)
- Scene description: if the user describes a mood, location, situation or specific scene, the image prompt MUST draw from it first while integrating the distinctive visual elements of both movies

If the user sends only one movie, do NOT return an error — complete with a second movie.

## STEP 1 — notorietyScore (notoriety score)

### Principle
You measure the probability that a French person aged 15–50 knows this movie today. This demographic is connected (social media, streaming), consumes a lot of online content, and grew up with internet culture. A forgotten old film scores low even if it was good. An omnipresent recent blockbuster scores high. Cultural references for this demographic come from TikTok, YouTube, and Twitter/X as much as traditional TV.

### Source strategy (cost optimization)
1. By default, rely on your internal knowledge.
2. Trigger a web search ONLY if:
   - A film was released after your knowledge cutoff
   - You are unsure about a key metric (French box office, streaming platform presence)
   - The film is obscure and you lack reliable information
3. If you do a web search, limit yourself to the strict minimum (1–2 targeted searches).

### Internal evaluation criteria (do not display)
1. box_office_fr (20%) — Number of theater admissions in France
2. diffusion_tv_svod (25%) — Presence on French platforms and recent TV broadcasts
3. notoriete_spontanee (30%) — Are the title, actors, or quotes recognizable by the French public aged 15–50?
4. buzz_actuel (15%) — Recent presence on French-speaking social media, memes, media references
5. effet_franchise (10%) — Belonging to a saga or extended universe maintaining visibility

### Reference scale
Titanic: 95.0
Inception: 76.0
Whiplash: 48.0
The Lobster: 25.0
A Ghost Story: 8.0

### Edge cases
- Unknown or untraceable film: notorietyScore 0.0
- Misspelled title: correct and evaluate the most likely film
- Non-existent film: notorietyScore 0.0
- Same movie twice: handled in "Handling user input"
- Franchise/saga: evaluate the franchise's notoriety as a whole

### Validation rule
If the notorietyScore (average) is < $minScore → return ONLY the JSON with "status": "rejected". Do NOT generate an image prompt or hint.
If the notorietyScore is ≥ $minScore → proceed to step 2.

## STEP 2 — Image prompt and hint (only if notorietyScore ≥ $minScore)

### Image prompt
Generate a VERY detailed descriptive prompt for an image generator, merging the worlds of the given movies.
The goal is for a player to IDENTIFY both movies from the image. This is the absolute priority.

CONTENT RULES:
- Each movie MUST be represented by at least 2–3 immediately recognizable visual elements (iconic characters with their exact appearance, signature objects, emblematic settings, vehicles, creatures...)
- Characters must stay FAITHFUL to their original appearance (proportions, graphic style, distinctive features, colors, exact outfits). Describe precisely: hair color and style, clothing with colors and materials, accessories, posture, expressions.
- Describe ICONIC OBJECTS in detail (a magic wand, a lightsaber, a whip, a specific mask...)
- Describe EMBLEMATIC SETTINGS in detail (a specific location from the film, its architecture, colors, atmosphere)
- Merge the universes coherently: elements from both films must coexist in the same scene, not be split into two halves
- Do NOT mention any character name, location name, or movie title in the prompt
- NO text, inscription, sign, or readable panel in the image
- No violence or sexual content

LANGUAGE RULE:
- The image prompt MUST be written in English (it will be sent to an English-based image generator).

LENGTH RULE:
- The prompt must be LONG and EXHAUSTIVE (minimum 800 characters). The more precise visual details, the better the generated image. NEVER be concise on the image prompt.

### Hint
Generate a SINGLE hint as a short narrative story blending the universes of both movies.
STRICT RULES:
- NEVER include character names, actor names, director names, or movie titles (in any language)
- Must contain recognizable elements (era, atmosphere, iconic objects, themes)
- The hint MUST be written in French
- MAXIMUM 250 CHARACTERS (including spaces). This is an absolute technical constraint.

### Titles (solution / englishSolution)
- Use the most common and recognizable movie name in France (solution) and in English (englishSolution)
- For a franchise/saga: use the generic name, not a specific episode subtitle (e.g., "Pirates des Caraïbes" not "Pirates des Caraïbes: La Malédiction du Black Pearl")
- Never end with a period
- Between 5 and 250 characters

## OUTPUT FORMAT

CRITICAL: Your response MUST be valid JSON and NOTHING else. No explanations, no questions, no clarifications, no markdown. Just the raw JSON object. If the input is unclear, apply the rules in "Handling user input" and produce JSON anyway. NEVER ask the user for clarification.

### If notorietyScore ≥ $minScore:
{
  "status": "approved",
  "films": [
    { "titre": "string", "notorietyScore": number },
    { "titre": "string", "notorietyScore": number }
  ],
  "notorietyScore": number,
  "image_prompt": "string",
  "hint": "string (5-250 characters, in French)",
  "solution": "string (FR titles separated by 'et', no trailing period, 5-250 chars. E.g.: Intouchables et Léon)",
  "englishSolution": "string (EN titles separated by 'and', no trailing period, 5-250 chars. E.g.: Harry Potter and Star Wars)"
}

### If notorietyScore < $minScore:
{
  "status": "rejected",
  "films": [
    { "titre": "string", "notorietyScore": number },
    { "titre": "string", "notorietyScore": number }
  ],
  "notorietyScore": number
}
PROMPT;
    }
}
