<?php

namespace App\Service;

use App\Entity\Game;

class GameService
{
    public function validateAnswer(Game $game, string $answer): Game|false
    {
        // Normalisation des chaînes
        $expectedWords = $this->normalizeAndExtractWords($game->getTitle());
        $userWords = $this->normalizeAndExtractWords($answer);

        // Vérification 80% tous les mots importants de la réponse attendue sont présents
        if ($this->wordsMatch($expectedWords, $userWords)) {
            return $game;
        }

        return false;
    }

    private function normalizeAndExtractWords(string $text): array
    {
        // Conversion en minuscules et suppression des accents
        $text = strtolower($this->removeAccents($text));

        // Suppression des mots vides (articles, prépositions...)
        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'à', 'au', 'aux', 'en', 'dans'];

        // Extraction des mots (lettres seulement)
        preg_match_all('/[a-z]+/', $text, $matches);
        $words = $matches[0];

        // Filtrage des mots vides et mots trop courts
        return array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords);
        });
    }

    private function wordsMatch(array $expectedWords, array $userWords): bool
    {
        if (empty($expectedWords)) {
            return false;
        }

        $matchedWords = 0;
        $totalExpectedWords = count($expectedWords);

        foreach ($expectedWords as $expectedWord) {
            foreach ($userWords as $userWord) {
                // Match exact ou similaire (Levenshtein pour les fautes de frappe)
                if ($expectedWord === $userWord || $this->isSimilar($expectedWord, $userWord)) {
                    $matchedWords++;
                    break;
                }
            }
        }

        // Au moins 80% des mots importants doivent matcher
        return ($matchedWords / $totalExpectedWords) >= 0.8;
    }

    private function isSimilar(string $word1, string $word2): bool
    {
        // Tolérance pour les fautes de frappe (distance de Levenshtein)
        $maxLength = max(strlen($word1), strlen($word2));
        $distance = levenshtein($word1, $word2);

        // Tolérance : 1 caractère différent pour mots < 6 chars, 2 pour mots plus longs
        $tolerance = $maxLength < 6 ? 1 : 2;

        return $distance <= $tolerance;
    }

    private function removeAccents(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
}
