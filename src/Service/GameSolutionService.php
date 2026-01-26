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
    private const float RECALL_THRESHOLD = 0.8;
    private const float PRECISION_THRESHOLD = 0.3;

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

        $matchedCount = 0;

        foreach ($expectedWords as $expectedWord) {
            foreach ($testedWords as $testedWord) {
                if ($this->wordsAreEquivalent($expectedWord, $testedWord)) {
                    ++$matchedCount;
                    break;
                }
            }
        }

        $recall = $matchedCount / count($expectedWords);
        $precision = $matchedCount / count($testedWords);

        return $recall >= self::RECALL_THRESHOLD && $precision >= self::PRECISION_THRESHOLD;
    }

    /**
     * @return array<int, string>
     */
    private function extractWords(string $text): array
    {
        $normalized = strtolower($this->removeAccents($text));

        preg_match_all('/[a-z0-9]+/', $normalized, $matches);

        return $matches[0];
    }

    private function wordsAreEquivalent(string $word1, string $word2): bool
    {
        return $word1 === $word2 || $this->isSimilar($word1, $word2);
    }

    private function isSimilar(string $word1, string $word2): bool
    {
        $maxLen = max(strlen($word1), strlen($word2));
        $tolerance = $maxLen < 6 ? 1 : 2;

        return levenshtein($word1, $word2) <= $tolerance;
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
