<?php

namespace App\Service;

use App\Entity\Game;
use App\Entity\User;

readonly class GameAnswerService
{
    public function __construct(
        private AchievementService $historyService,
        private UserService $userService)
    {
    }

    public function validateAnswer(Game $game, ?User $user, string $answer): Game|false
    {
        $expectedWords = $this->normalizeAndExtractWords($game->getSolution());
        $userWords = $this->normalizeAndExtractWords($answer);

        if ($this->wordsMatch($expectedWords, $userWords) >= 0.8) {
            if ($user) {
                $this->historyService->saveResult($game);
                $this->userService->addPoints($user, $game->getDifficulty());
            }

            return $game;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAndExtractWords(string $text): array
    {
        $text = strtolower($this->removeAccents($text));

        $stopWords = ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'à', 'au', 'aux', 'en', 'dans'];

        preg_match_all('/[a-z]+/', $text, $matches);
        $words = $matches[0];

        return array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords, true);
        });
    }

    /**
     * @param array<int, string> $expectedWords
     * @param array<int, string> $userWords
     */
    private function wordsMatch(array $expectedWords, array $userWords): float
    {
        if (empty($expectedWords)) {
            return 0;
        }

        $matchedWords = 0;
        $totalExpectedWords = count($expectedWords);

        foreach ($expectedWords as $expectedWord) {
            foreach ($userWords as $userWord) {
                if ($expectedWord === $userWord || $this->isSimilar($expectedWord, $userWord)) {
                    ++$matchedWords;
                    break;
                }
            }
        }

        return $matchedWords / $totalExpectedWords;
    }

    private function isSimilar(string $word1, string $word2): bool
    {
        $maxLength = max(strlen($word1), strlen($word2));
        $distance = levenshtein($word1, $word2);
        $tolerance = $maxLength < 6 ? 1 : 2;

        return $distance <= $tolerance;
    }

    private function removeAccents(string $text): string
    {
        return iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    }
}
