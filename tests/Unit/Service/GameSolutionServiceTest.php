<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Repository\AchievementRepository;
use App\Service\AchievementService;
use App\Service\GameSolutionService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class GameSolutionServiceTest extends TestCase
{
    private GameSolutionService $service;
    private EntityManagerInterface $entityManager;
    private AchievementService $achievementService;
    private UserService $userService;
    private AchievementRepository $achievementRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->achievementService = $this->createMock(AchievementService::class);
        $this->userService = $this->createMock(UserService::class);
        $this->achievementRepository = $this->createMock(AchievementRepository::class);

        $this->service = new GameSolutionService(
            $this->entityManager,
            $this->achievementService,
            $this->userService,
            $this->achievementRepository
        );
    }

    // ========== Tests wordsMatch() - Exact match ==========

    public function testWordsMatchWithExactMatch(): void
    {
        $result = $this->service->wordsMatch('Retour vers le futur', 'Retour vers le futur');

        self::assertTrue($result);
    }

    public function testWordsMatchWithDifferentCase(): void
    {
        $result = $this->service->wordsMatch('Retour vers le futur', 'RETOUR VERS LE FUTUR');

        self::assertTrue($result);
    }

    public function testWordsMatchRemovesAccents(): void
    {
        $result = $this->service->wordsMatch('Lé Fäbuleûx Dèstïn', 'Le Fabuleux Destin');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Typos (Levenshtein) ==========

    public function testWordsMatchWithTypo(): void
    {
        $result = $this->service->wordsMatch('Matrix', 'Matix');

        self::assertTrue($result);
    }

    public function testWordsMatchWithMultipleTypos(): void
    {
        $result = $this->service->wordsMatch('Matrix', 'Mtx');

        self::assertFalse($result);
    }

    public function testWordsMatchWithSpecialCharacters(): void
    {
        $result = $this->service->wordsMatch('Spider-Man', 'Spider Man');

        self::assertTrue($result);
    }

    public function testWordsMatchToleratesOneCharForShortWords(): void
    {
        $result = $this->service->wordsMatch('Leon', 'Leon');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Leon', 'Leom');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Leon', 'Lexx');
        self::assertFalse($result);
    }

    public function testWordsMatchTolerateTwoCharsForLongWords(): void
    {
        $result = $this->service->wordsMatch('Interstellar', 'Intersteler');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Interstellar', 'Interstlar');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Interstellar', 'Intrstar');
        self::assertFalse($result);
    }

    // ========== Tests wordsMatch() - Recall threshold (80%) ==========

    public function testWordsMatchWith80PercentThreshold(): void
    {
        $result = $this->service->wordsMatch('Star Wars Episode Four', 'Star Wars Episode');

        self::assertFalse($result); // 75% recall
    }

    public function testWordsMatchExactly80Percent(): void
    {
        $result = $this->service->wordsMatch('Harry Potter Chamber Secrets Five', 'Harry Potter Chamber Secrets');

        self::assertTrue($result); // 80% recall
    }

    // ========== Tests wordsMatch() - Precision threshold (30%) ==========

    public function testWordsMatchRejectsSpam(): void
    {
        $spam = 'Matrix Inception Avatar Titanic Alien Predator Terminator Rocky Rambo Jaws';

        $result = $this->service->wordsMatch('Matrix Inception', $spam);

        self::assertFalse($result); // recall=100%, precision=20%
    }

    public function testWordsMatchAcceptsReasonableExtraWords(): void
    {
        $result = $this->service->wordsMatch('Matrix Inception', 'Matrix et Inception film');

        self::assertTrue($result); // recall=100%, precision=50%
    }

    public function testWordsMatchPrecisionAt30Percent(): void
    {
        // 2 mots attendus, joueur tape ~6 mots → precision ~33%
        $result = $this->service->wordsMatch('Matrix Inception', 'Matrix Inception un deux trois quatre');

        self::assertTrue($result);
    }

    public function testWordsMatchPrecisionBelow30Percent(): void
    {
        // 2 mots attendus, joueur tape 8 mots → precision = 25%
        $result = $this->service->wordsMatch('Matrix Inception', 'Matrix Inception un deux trois quatre cinq six');

        self::assertFalse($result);
    }

    // ========== Tests wordsMatch() - All words count ==========

    public function testWordsMatchAllWordsCount(): void
    {
        // Sans stop words filter, tous les mots comptent
        $result = $this->service->wordsMatch('Le Seigneur des Anneaux', 'Seigneur Anneaux');

        self::assertFalse($result); // recall = 2/4 = 50%
    }

    public function testWordsMatchLaLaLand(): void
    {
        $result = $this->service->wordsMatch('La La Land', 'La La Land');

        self::assertTrue($result);
    }

    public function testWordsMatchShortWordsIncluded(): void
    {
        $result = $this->service->wordsMatch('Up', 'Up');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Edge cases ==========

    public function testWordsMatchEmptyExpected(): void
    {
        $result = $this->service->wordsMatch('', 'Matrix');

        self::assertFalse($result);
    }

    public function testWordsMatchEmptyTested(): void
    {
        $result = $this->service->wordsMatch('Matrix', '');

        self::assertFalse($result);
    }

    // ========== Tests handleAnswer() ==========

    public function testHandleAnswerReturnsFalseWhenNoMatch(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix Inception');
        $game->method('getEnglishSolution')->willReturn('The Matrix and Inception');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(1);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->handleAnswer($game, 'Avatar', null);

        self::assertFalse($result);
    }

    public function testHandleAnswerMatchesFrenchSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Retour vers le futur');
        $game->method('getEnglishSolution')->willReturn('Back to the Future');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Retour vers le futur', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerMatchesEnglishSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Retour vers le futur');
        $game->method('getEnglishSolution')->willReturn('Back to the Future');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Back to the Future', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerIncrementsAttempts(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(5);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(6);

        $this->entityManager->expects(self::once())->method('flush');

        $this->service->handleAnswer($game, 'Wrong answer', null);
    }
}
