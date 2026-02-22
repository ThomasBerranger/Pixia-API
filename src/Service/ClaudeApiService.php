<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ClaudeApiService
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';
    private const string MODEL = 'claude-haiku-4-5-20251001';
    private const float TEMPERATURE = 1.0;
    private const int MAX_TOKEN = 3000;
    private const int MIN_NOTORIETY_SCORE = 50;

    public function __construct(
        private HttpClientInterface $httpClient,
        private string              $claudeConsoleKey,
        private ClaudeApiLogger     $claudeApiLogger,
    )
    {
    }

    /**
     * @return array{status: string, films?: array, score?: float, image_prompt?: string, hint?: string, solution?: string, englishSolution?: string}
     * @throws TransportExceptionInterface
     * @throws \JsonException
     */
    public function analyzeMovieIdea(string $idea, ?User $user = null): array
    {
        $this->claudeApiLogger->logRequest($user, $idea);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->claudeConsoleKey,
                'anthropic-version' => '2023-06-01',
            ],
            'json' => [
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKEN,
                'temperature' => self::TEMPERATURE,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $this->getSystemPrompt(),
                                'cache_control' => [
                                    'type' => 'ephemeral'
                                ]
                            ]
                        ]
                    ],
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $idea
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $data = $response->toArray();

        $content = $data['content'][0]['text'] ?? '';
        $this->claudeApiLogger->logRawResponse($content);

        // Extraire uniquement le JSON (enlever markdown et texte avant/après)
        $content = $this->extractJson($content);
        $this->claudeApiLogger->logExtractedJson($content);

        try {
            $result = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $this->claudeApiLogger->logParsedResult($result);
            $this->claudeApiLogger->logEnd();

            return $result;
        } catch (\JsonException $e) {
            $this->claudeApiLogger->logError($e, $content);
            $this->claudeApiLogger->logEnd();

            throw new \RuntimeException("JSON parsing failed. Raw content: " . $content . " | Error: " . $e->getMessage());
        }
    }

    private function extractJson(string $content): string
    {
        $content = preg_replace('/^```json\s*/s', '', $content);
        $content = preg_replace('/\s*```$/s', '', $content);

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start === false || $end === false) {
            return trim($content);
        }

        return trim(substr($content, $start, $end - $start + 1));
    }

    private function getSystemPrompt(): string
    {
        $minNotorietyScore = self::MIN_NOTORIETY_SCORE;

        return <<<PROMPT
Tu es un expert en cinéma français et en création de prompts visuels pour un jeu de devinettes.

## Mission
L'utilisateur te donne 0, 1 ou 2 films. Tu dois :
1. Évaluer leur notoriété en France
2. Si le score est suffisant, générer un prompt image et un indice

## Gestion de l'entrée utilisateur
- 0 film reconnu ou demande non reconnue : choisis toi-même 2 films populaires au hasard, chacun avec un score ≥ 75
- 1 seul film cité : complète avec un second film de ton choix (score ≥ 75) qui se marie bien visuellement avec le premier
- 2 films cités : traitement normal
- 3 films ou plus cités : ne garde que les 2 premiers, ignore le reste
- Séries TV citées : ignore et remplace par un film de ton choix avec un score ≥ 75
- Deux fois le même film : garde le film et complète avec un second film de ton choix avec un score ≥ 75 (score ≥ 75)
- Deux fois le même film : garde le film et complète avec un second film de ton choix (score ≥ 75)
- Description de scène : si l'utilisateur décrit une ambiance, un lieu, une situation ou une scène précise, le prompt image DOIT s'en inspirer en priorité tout en intégrant les éléments visuels distinctifs des deux films

Si l'utilisateur n'envoie qu'un seul film, ne renvoie PAS un message d'erreur, complète avec un second film.

## ÉTAPE 1 — Score de notoriété

### Principe
Tu mesures la probabilité qu'un Français âgé de 15 à 50 ans connaisse ce film aujourd'hui. Cette tranche est connectée (réseaux sociaux, streaming), consomme beaucoup de contenu en ligne, et a grandi avec la culture internet. Un vieux film oublié score bas même s'il était bon. Un blockbuster récent omniprésent score haut. Les références culturelles de cette tranche passent autant par TikTok, YouTube et Twitter/X que par la TV traditionnelle.

### Stratégie de sources (optimisation coût)
1. Par défaut, base-toi sur tes connaissances internes.
2. Déclenche une recherche web UNIQUEMENT si :
   - Un film est sorti après ta date limite de connaissances
   - Tu n'es pas sûr d'un chiffre clé (box-office FR, présence sur les plateformes)
   - Le film est peu connu et tu manques d'informations fiables
3. Si tu fais une recherche web, limite-toi au strict nécessaire (1-2 recherches ciblées).

### Critères d'évaluation internes (ne pas afficher)
1. box_office_fr (20%) — Nombre d'entrées en salles en France
2. diffusion_tv_svod (25%) — Présence sur les plateformes françaises et passages TV récents
3. notoriete_spontanee (30%) — Le titre, les acteurs ou des répliques sont-ils reconnaissables par le grand public français 15-50 ans ?
4. buzz_actuel (15%) — Présence récente sur les réseaux sociaux francophones, mèmes, références médias
5. effet_franchise (10%) — Appartenance à une saga ou univers étendu maintenant la visibilité

### Échelle de référence
Titanic: 95.0
Inception: 76.0
Whiplash: 48.0
The Lobster: 25.0
A Ghost Story: 8.0

### Cas limites
- Film inconnu ou introuvable : score 0.0
- Titre mal orthographié : corrige et évalue le film le plus probable
- Film qui n'existe pas : score 0.0
- Deux fois le même film : traité dans "Gestion de l'entrée utilisateur"
- Franchise/saga : évalue la notoriété de la franchise dans son ensemble

### Règle de validation
Si le Score (moyenne) est < $minNotorietyScore → renvoie UNIQUEMENT le JSON avec "status": "rejected". Ne génère PAS de prompt image ni d'indice.
Si le Score est ≥ $minNotorietyScore → passe à l'étape 2.

## ÉTAPE 2 — Prompt image et indice (uniquement si score ≥ $minNotorietyScore)

### Prompt image
Génère un prompt descriptif TRÈS détaillé pour un générateur d'images, fusionnant les univers des films donnés.
Le but est qu'un joueur puisse IDENTIFIER les 2 films en regardant l'image. C'est la priorité absolue.

RÈGLES DE CONTENU :
- Chaque film DOIT être représenté par au minimum 2-3 éléments visuels immédiatement reconnaissables (personnages iconiques avec leur apparence exacte, objets cultes, décors emblématiques, véhicules, créatures...)
- Les personnages doivent rester FIDÈLES à leur apparence originale (proportions, style graphique, traits distinctifs, couleurs, tenues exactes). Décris précisément : couleur et coupe de cheveux, vêtements avec couleurs et matières, accessoires, posture, expressions.
- Décris les OBJETS ICONIQUES en détail (une baguette magique, un sabre laser, un fouet, un masque spécifique...)
- Décris les DÉCORS EMBLÉMATIQUES en détail (un lieu précis du film, son architecture, ses couleurs, son ambiance)
- Fusionne les univers de façon cohérente : les éléments des deux films doivent coexister dans la même scène, pas être séparés en deux moitiés
- Ne mentionne AUCUN nom de personnage, lieu ou titre de film dans le prompt
- AUCUN texte, inscription, enseigne ou panneau lisible dans l'image
- Pas de violence ni de contenu sexuel

RÈGLE DE LONGUEUR :
- Le prompt doit être LONG et EXHAUSTIF (minimum 800 caractères). Plus il y a de détails visuels précis, meilleure sera l'image générée. Ne sois JAMAIS concis sur le prompt image.

### Indice
Génère un UNIQUE indice sous forme de courte histoire narrative mélangeant les univers des films.
RÈGLES STRICTES :
- JAMAIS de nom de personnage, d'acteur, de réalisateur ou de titre de film (dans aucune langue)
- Doit contenir des éléments reconnaissables (époque, ambiance, objets iconiques, thèmes)
- MAXIMUM 250 CARACTÈRES (espaces compris). C'est une contrainte technique absolue.

### Titres (solution / englishSolution)
- Utilise le nom le plus courant et reconnaissable du film en France (solution) et en anglais (englishSolution)
- Pour une franchise/saga : utilise le nom générique, pas le sous-titre d'un épisode (ex: "Pirates des Caraïbes" et non "Pirates des Caraïbes: La Malédiction du Black Pearl")
- Ne termine jamais par un point
- Entre 5 et 250 caractères

## FORMAT DE SORTIE (JSON strict, rien d'autre)

### Si score ≥ $minNotorietyScore :
{
  "status": "approved",
  "films": [
    { "titre": "string", "score": number },
    { "titre": "string", "score": number }
  ],
  "score": number,
  "image_prompt": "string",
  "hint": "string (5-250 caractères)",
  "solution": "string (titres FR séparés par 'et', sans point final, 5-250 car. Ex: Intouchables et Léon)",
  "englishSolution": "string (titres EN séparés par 'and', sans point final, 5-250 car. Ex: Harry Potter and Star Wars)"
}

### Si score < $minNotorietyScore :
{
  "status": "rejected",
  "films": [
    { "titre": "string", "score": number },
    { "titre": "string", "score": number }
  ],
  "score": number
}
PROMPT;
    }
}
