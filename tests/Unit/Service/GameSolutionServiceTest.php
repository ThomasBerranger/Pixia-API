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

    public function testWordsMatchWithSpecialCharacters(): void
    {
        $result = $this->service->wordsMatch('Spider-Man', 'Spider Man');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Word order ==========

    public function testWordsMatchWithDifferentOrder(): void
    {
        $result = $this->service->wordsMatch('Le Roi Lion', 'Lion Roi Le');

        self::assertTrue($result);
    }

    public function testWordsMatchWithMixedOrder(): void
    {
        $result = $this->service->wordsMatch('Le Roi Lion et Babe', 'Babe et Lion Roi Le');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Word consumption ==========

    public function testWordsMatchRequiresDuplicateWords(): void
    {
        $result = $this->service->wordsMatch('La La Land', 'La Land');

        self::assertFalse($result);
    }

    public function testWordsMatchWithDuplicateWordsProvided(): void
    {
        $result = $this->service->wordsMatch('La La Land', 'La La Land');

        self::assertTrue($result);
    }

    public function testWordsMatchConsumesWords(): void
    {
        $result = $this->service->wordsMatch('New York New York', 'New York');

        self::assertFalse($result);
    }

    public function testWordsMatchWithExtraWordsInResponse(): void
    {
        $result = $this->service->wordsMatch('Le Roi Lion', 'Le Roi Lion Film');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Typos tolerance by word length ==========

    public function testWordsMatchNoToleranceForVeryShortWords(): void
    {
        // <= 3 chars: exact match required
        $result = $this->service->wordsMatch('Up', 'Us');
        self::assertFalse($result);

        $result = $this->service->wordsMatch('It', 'Is');
        self::assertFalse($result);

        $result = $this->service->wordsMatch('Ca', 'Sa');
        self::assertFalse($result);
    }

    public function testWordsMatchExactMatchForShortWords(): void
    {
        $result = $this->service->wordsMatch('Up', 'Up');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('It', 'It');
        self::assertTrue($result);
    }

    public function testWordsMatchToleratesOneCharForMediumWords(): void
    {
        // 4-5 chars: 1 error tolerated
        $result = $this->service->wordsMatch('Leon', 'Leom');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Rocky', 'Roky');
        self::assertTrue($result);
    }

    public function testWordsMatchRejectsTwoErrorsForMediumWords(): void
    {
        $result = $this->service->wordsMatch('Leon', 'Lexx');
        self::assertFalse($result);

        $result = $this->service->wordsMatch('Rocky', 'Rxxy');
        self::assertFalse($result);
    }

    public function testWordsMatchTolerateTwoCharsForLongWords(): void
    {
        // 6+ chars: 2 errors tolerated
        $result = $this->service->wordsMatch('Interstellar', 'Intersteler');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Interstellar', 'Interstlar');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Chihiro', 'Shihiro');
        self::assertTrue($result);
    }

    public function testWordsMatchRejectsThreeErrorsForLongWords(): void
    {
        $result = $this->service->wordsMatch('Interstellar', 'Intrstar');
        self::assertFalse($result);
    }

    // ========== Tests wordsMatch() - Numbers exact match ==========

    public function testWordsMatchNumbersRequireExactMatch(): void
    {
        $result = $this->service->wordsMatch('1917', '1918');
        self::assertFalse($result);

        $result = $this->service->wordsMatch('300', '301');
        self::assertFalse($result);

        $result = $this->service->wordsMatch('2001', '2002');
        self::assertFalse($result);
    }

    public function testWordsMatchNumbersExactMatch(): void
    {
        $result = $this->service->wordsMatch('1917', '1917');
        self::assertTrue($result);

        $result = $this->service->wordsMatch('Apollo 13', 'Apollo 13');
        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - Max words limit (anti-spam) ==========

    public function testWordsMatchRejectsTooManyWords(): void
    {
        // Expected: 2 words, Max allowed: 4 words (2x multiplier)
        $spam = 'Matrix Inception Avatar Titanic Alien';

        $result = $this->service->wordsMatch('Matrix Inception', $spam);

        self::assertFalse($result);
    }

    public function testWordsMatchAcceptsWithinLimit(): void
    {
        // Expected: 2 words, Response: 4 words (exactly 2x)
        $result = $this->service->wordsMatch('Matrix Inception', 'Matrix et Inception film');

        self::assertTrue($result);
    }

    // ========== Tests wordsMatch() - All words must be found ==========

    public function testWordsMatchRequiresAllWords(): void
    {
        $result = $this->service->wordsMatch('Le Roi Lion', 'Le Roi');

        self::assertFalse($result);
    }

    public function testWordsMatchAllWordsFound(): void
    {
        $result = $this->service->wordsMatch('Le Roi Lion', 'Le Roi Lion');

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
