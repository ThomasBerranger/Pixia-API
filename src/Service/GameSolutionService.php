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
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AchievementService     $achievementService,
        private UserService            $userService,
        private AchievementRepository  $achievementRepository,
    )
    {
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

    public function wordsMatch(string $expectedWords, string $testedWords): bool
    {
        $normalizedExpectedWords = $this->normalizeAndExtractWords($expectedWords);
        $normalizedTestedWords = $this->normalizeAndExtractWords($testedWords);

        if (empty($normalizedExpectedWords)) {
            return false;
        }

        $matchedWords = 0;
        $totalExpectedWords = count($normalizedExpectedWords);

        foreach ($normalizedExpectedWords as $normalizedExpectedWord) {
            foreach ($normalizedTestedWords as $normalizedTestedWord) {
                if ($normalizedExpectedWord === $normalizedTestedWord || $this->isSimilar($normalizedExpectedWord, $normalizedTestedWord)) {
                    ++$matchedWords;
                    break;
                }
            }
        }

        return $matchedWords / $totalExpectedWords >= 0.8;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAndExtractWords(string $text): array
    {
        $text = strtolower($this->removeAccents($text));

        $stopWords = [
            // Français
            'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'à', 'au', 'aux', 'en', 'dans',
            // Anglais
            'the', 'and', 'for', 'with', 'from', 'into', 'this', 'that', 'these', 'those'
        ];

        preg_match_all('/[a-z]+/', $text, $matches);
        $words = $matches[0];

        return array_filter($words, function ($word) use ($stopWords) {
            return strlen($word) > 2 && !in_array($word, $stopWords, true);
        });
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
        $normalized = \Normalizer::normalize($text, \Normalizer::NFD);

        if (!is_string($normalized)) {
            return $text;
        }

        $result = preg_replace('/\p{Mn}/u', '', $normalized);

        return is_string($result) ? $result : $text;
    }
}
