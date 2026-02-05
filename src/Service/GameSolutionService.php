<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Repository\AchievementRepository;
use Doctrine\ORM\EntityManagerInterface;

readonly class GameSolutionService
{
    private const int MAX_WORDS_MULTIPLIER = 2;
    private const array IGNORED_WORDS = ['et', 'and'];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AchievementService     $achievementService,
        private UserService            $userService,
        private AchievementRepository  $achievementRepository,
    ) {
    }

    public function handleAnswer(Game $game, string $answer, ?User $player): Game|false
    {
        $game->setAttempts($game->getAttempts() + 1);

        if (!$this->isCorrectAnswer($game, $answer)) {
            $this->entityManager->flush();
            return false;
        }

        if ($player && !$this->hasAlreadyWon($player, $game)) {
            $this->handleWin($game, $player);
        }

        $this->entityManager->flush();

        return $game;
    }

    private function isCorrectAnswer(Game $game, string $answer): bool
    {
        return $this->wordsMatch($game->getSolution(), $answer)
            || $this->wordsMatch($game->getEnglishSolution(), $answer);
    }

    private function hasAlreadyWon(User $player, Game $game): bool
    {
        return $this->achievementRepository->findOneBy(['player' => $player, 'game' => $game]) instanceof Achievement;
    }

    private function handleWin(Game $game, User $player): void
    {
        $this->achievementService->saveResult($player, $game);
        $this->userService->addGamePoints($player, $game);
    }

    public function wordsMatch(string $expected, string $tested): bool
    {
        $expectedWords = $this->extractWords($expected);
        $testedWords = $this->extractWords($tested);

        if (empty($expectedWords) || empty($testedWords)) {
            return false;
        }

        $maxAllowedWords = count($expectedWords) * self::MAX_WORDS_MULTIPLIER;
        if (count($testedWords) > $maxAllowedWords) {
            return false;
        }

        $usedIndices = [];

        foreach ($expectedWords as $expectedWord) {
            $found = false;

            foreach ($testedWords as $index => $testedWord) {
                if (in_array($index, $usedIndices, true)) {
                    continue;
                }

                if ($this->wordsAreEquivalent($expectedWord, $testedWord)) {
                    $usedIndices[] = $index;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function extractWords(string $text): array
    {
        $normalized = strtolower($this->removeAccents($text));

        preg_match_all('/[a-z0-9]+/', $normalized, $matches);

        return array_values(array_filter(
            $matches[0],
            static fn (string $word): bool => !in_array($word, self::IGNORED_WORDS, true)
        ));
    }

    private function wordsAreEquivalent(string $word1, string $word2): bool
    {
        return $word1 === $word2 || $this->isSimilar($word1, $word2);
    }

    private function isSimilar(string $word1, string $word2): bool
    {
        if ($this->isNumeric($word1) || $this->isNumeric($word2)) {
            return false;
        }

        $maxLen = max(strlen($word1), strlen($word2));

        if ($maxLen <= 3) {
            return false;
        }

        $tolerance = $maxLen <= 5 ? 1 : 2;

        return levenshtein($word1, $word2) <= $tolerance;
    }

    private function isNumeric(string $word): bool
    {
        return preg_match('/^\d+$/', $word) === 1;
    }

    private function removeAccents(string $text): string
    {
        $normalized = \Normalizer::normalize($text, \Normalizer::NFD);

        if (!is_string($normalized)) {
            return $text;
        }

        $result = preg_replace('/\p{Mn}/u', '', $normalized);

        return is_string($result) ? $result : $text;
    }
}
