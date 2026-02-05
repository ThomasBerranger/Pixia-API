<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Repository\AchievementRepository;
use App\Service\AchievementService;
use App\Service\GameSolutionService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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

    // ========================================================================
    // REAL USE CASES - 2 films combined
    // ========================================================================

    #[DataProvider('twoFilmsCombinedProvider')]
    public function testWordsMatchWithTwoFilmsCombined(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function twoFilmsCombinedProvider(): array
    {
        return [
            // Exact matches
            'exact match FR' => ['Le Roi Lion et Babe', 'Le Roi Lion et Babe', true],
            'exact match EN' => ['The Lion King and Babe', 'The Lion King and Babe', true],

            // Different order (valid)
            'reversed order' => ['Le Roi Lion et Babe', 'Babe et Le Roi Lion', true],
            'mixed order' => ['Le Roi Lion et Babe', 'Babe Lion Roi Le et', true],

            // Missing one film (invalid)
            'only first film' => ['Le Roi Lion et Babe', 'Le Roi Lion', false],
            'only second film' => ['Le Roi Lion et Babe', 'Babe', false],

            // With typos (valid)
            'typo in long word' => ['Interstellar et Inception', 'Intersteler et Incepton', true],
            'typo chihiro' => ['Le Voyage de Chihiro et Totoro', 'Le Voyage de Shihiro et Totoro', true],

            // Extra words within limit (valid)
            'extra words ok' => ['Matrix et Inception', 'Matrix film et Inception movie', true],

            // Real film combinations
            'amelie poulain + intouchables' => ['Amelie Poulain et Intouchables', 'Amelie Poulain et Intouchables', true],
            'amelie poulain + intouchables scrambled' => ['Amelie Poulain et Intouchables', 'Intouchables et Amelie Poulain', true],
            'back to future + ghostbusters' => ['Back to the Future et Ghostbusters', 'Ghostbusters et Back to the Future', true],
        ];
    }

    // ========================================================================
    // NORMALISATION - Case, accents, special chars
    // ========================================================================

    #[DataProvider('normalisationProvider')]
    public function testWordsMatchNormalisation(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function normalisationProvider(): array
    {
        return [
            // Case insensitive
            'uppercase answer' => ['Matrix', 'MATRIX', true],
            'lowercase answer' => ['MATRIX', 'matrix', true],
            'mixed case' => ['The Matrix', 'tHe MaTrIx', true],

            // Accents
            'accents in solution' => ['Amélie Poulain', 'Amelie Poulain', true],
            'accents in answer' => ['Amelie Poulain', 'Amélie Poulain', true],
            'both have accents' => ['Léon', 'Léon', true],
            'complex accents' => ['Lé Fäbuleûx Dèstïn', 'Le Fabuleux Destin', true],

            // Special characters become word separators
            'hyphen' => ['Spider-Man', 'Spider Man', true],
            'apostrophe splits' => ["Ocean's Eleven", "Ocean's Eleven", true],
            'colon' => ['Star Wars: A New Hope', 'Star Wars A New Hope', true],
            'dot' => ['Dr. Strange', 'Dr Strange', true],

            // Wall-E special case
            'wall-e exact' => ['Wall-E', 'Wall E', true],
            'wall-e with hyphen' => ['Wall-E', 'Wall-E', true],
        ];
    }

    // ========================================================================
    // WORD CONSUMPTION - Duplicate words
    // ========================================================================

    #[DataProvider('wordConsumptionProvider')]
    public function testWordsMatchWordConsumption(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function wordConsumptionProvider(): array
    {
        return [
            // Duplicate words in solution
            'la la land complete' => ['La La Land', 'La La Land', true],
            'la la land missing one la' => ['La La Land', 'La Land', false],
            'new york new york complete' => ['New York New York', 'New York New York', true],
            'new york new york incomplete' => ['New York New York', 'New York', false],

            // Duplicate words in answer (extra, within limit)
            'extra duplicate ok' => ['Matrix', 'Matrix Matrix', true],
            'triple word over limit' => ['Inception', 'Inception Inception Inception', false],

            // Both have duplicates
            'matching duplicates' => ['Bang Bang', 'Bang Bang', true],
            'insufficient duplicates' => ['Bang Bang Bang', 'Bang Bang', false],
        ];
    }

    // ========================================================================
    // TOLERANCE - By word length
    // ========================================================================

    #[DataProvider('toleranceProvider')]
    public function testWordsMatchTolerance(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function toleranceProvider(): array
    {
        return [
            // <= 3 chars: NO tolerance (exact match)
            'up exact' => ['Up', 'Up', true],
            'up typo' => ['Up', 'Us', false],
            'it exact' => ['It', 'It', true],
            'it typo' => ['It', 'Is', false],
            'ca exact' => ['Ca', 'Ca', true],
            'ca typo' => ['Ca', 'Sa', false],
            'the exact' => ['The', 'The', true],
            'the typo' => ['The', 'Tho', false],
            '3 chars exact' => ['Her', 'Her', true],
            '3 chars typo' => ['Her', 'Hir', false],

            // 4-5 chars: 1 error tolerated
            'leon 1 error' => ['Leon', 'Leom', true],
            'leon 2 errors' => ['Leon', 'Lexx', false],
            'rocky 1 error' => ['Rocky', 'Roky', true],
            'rocky 1 error sub' => ['Rocky', 'Ricky', true],
            'rocky 2 errors' => ['Rocky', 'Rxxy', false],
            'alien 1 error' => ['Alien', 'Aliem', true],
            'alien 2 errors' => ['Alien', 'Alixx', false],
            '4 chars boundary' => ['Jaws', 'Jows', true],
            '5 chars boundary' => ['Drive', 'Drivr', true],

            // 6+ chars: 2 errors tolerated
            'matrix 1 error' => ['Matrix', 'Matrik', true],
            'matrix 2 errors' => ['Matrix', 'Matrxx', true],
            'matrix 3 errors' => ['Matrix', 'Mxxxxx', false],
            'chihiro typo' => ['Chihiro', 'Shihiro', true],
            'chihiro 2 typos' => ['Chihiro', 'Shihira', true],
            'chihiro 3 typos' => ['Chihiro', 'Shihixx', false],
            'interstellar 2 errors' => ['Interstellar', 'Intersteler', true],
            'interstellar 3 errors' => ['Interstellar', 'Intrstar', false],
            'inception 2 errors' => ['Inception', 'Incepton', true],
            'gladiator 2 errors' => ['Gladiator', 'Gladiater', true],
        ];
    }

    // ========================================================================
    // NUMBERS - Exact match required
    // ========================================================================

    #[DataProvider('numbersProvider')]
    public function testWordsMatchNumbers(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function numbersProvider(): array
    {
        return [
            // Exact numbers
            '1917 exact' => ['1917', '1917', true],
            '300 exact' => ['300', '300', true],
            '2001 exact' => ['2001', '2001', true],
            'apollo 13 exact' => ['Apollo 13', 'Apollo 13', true],
            '21 jump street' => ['21 Jump Street', '21 Jump Street', true],

            // Similar numbers (must be rejected)
            '1917 vs 1918' => ['1917', '1918', false],
            '300 vs 301' => ['300', '301', false],
            '2001 vs 2002' => ['2001', '2002', false],
            'apollo 13 vs 14' => ['Apollo 13', 'Apollo 14', false],
            '21 vs 22 jump street' => ['21 Jump Street', '22 Jump Street', false],

            // Numbers in film titles
            'ocean 11' => ['Ocean Eleven', 'Ocean Eleven', true],
            'se7en' => ['Se7en', 'Se7en', true],
        ];
    }

    // ========================================================================
    // ANTI-SPAM - Max words limit
    // ========================================================================

    #[DataProvider('antiSpamProvider')]
    public function testWordsMatchAntiSpam(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function antiSpamProvider(): array
    {
        return [
            // Within limit (2x expected words)
            '2 words, 4 answer ok' => ['Matrix Inception', 'Matrix et Inception film', true],
            '3 words, 6 answer ok' => ['Le Roi Lion', 'Le Roi Lion est genial', true],
            '2 words, exactly 4' => ['Matrix Inception', 'un deux Matrix Inception', true],

            // Over limit
            '2 words, 5 answer' => ['Matrix Inception', 'Matrix Inception Avatar Alien Jaws', false],
            '3 words, 7 answer' => ['Le Roi Lion', 'Le Roi Lion un deux trois quatre', false],
            'spam multiple films' => ['Matrix', 'Matrix Inception Avatar Titanic', false],

            // Edge: exactly at limit
            '1 word, 2 answer' => ['Matrix', 'Matrix film', true],
            '1 word, 3 answer' => ['Matrix', 'Matrix film genial', false],
        ];
    }

    // ========================================================================
    // EDGE CASES
    // ========================================================================

    #[DataProvider('edgeCasesProvider')]
    public function testWordsMatchEdgeCases(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function edgeCasesProvider(): array
    {
        return [
            // Empty strings
            'empty solution' => ['', 'Matrix', false],
            'empty answer' => ['Matrix', '', false],
            'both empty' => ['', '', false],

            // Only special chars (no words extracted)
            'only special chars solution' => ['---', 'Matrix', false],
            'only special chars answer' => ['Matrix', '---', false],

            // Single word
            'single word exact' => ['Titanic', 'Titanic', true],
            'single word typo ok' => ['Titanic', 'Titanik', true],
            'single word wrong' => ['Titanic', 'Avatar', false],

            // Very long title
            'long title' => [
                'Le Fabuleux Destin Amelie Poulain',
                'Le Fabuleux Destin Amelie Poulain',
                true,
            ],
            'long title scrambled' => [
                'Le Fabuleux Destin Amelie Poulain',
                'Amelie Poulain Le Fabuleux Destin',
                true,
            ],

            // Whitespace variations
            'extra spaces' => ['The Matrix', 'The    Matrix', true],
            'tabs' => ["The Matrix", "The\tMatrix", true],
        ];
    }

    // ========================================================================
    // REAL FILM TITLES - Comprehensive tests
    // ========================================================================

    #[DataProvider('realFilmTitlesProvider')]
    public function testWordsMatchRealFilmTitles(string $solution, string $answer, bool $expected): void
    {
        $result = $this->service->wordsMatch($solution, $answer);

        self::assertSame($expected, $result);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function realFilmTitlesProvider(): array
    {
        return [
            // Short titles
            'up' => ['Up', 'Up', true],
            'it' => ['It', 'It', true],
            'her' => ['Her', 'Her', true],
            'jaws' => ['Jaws', 'Jaws', true],

            // Titles with hyphens
            'spider-man' => ['Spider-Man', 'Spider Man', true],
            'x-men' => ['X-Men', 'X Men', true],
            'wall-e' => ['Wall-E', 'Wall E', true],
            'ant-man' => ['Ant-Man', 'Ant Man', true],

            // Titles with numbers
            'se7en' => ['Se7en', 'Se7en', true],
            'district 9' => ['District 9', 'District 9', true],
            '12 angry men' => ['12 Angry Men', '12 Angry Men', true],

            // French titles with accents
            'amelie' => ['Amélie', 'Amelie', true],
            'leon pro' => ['Léon', 'Leon', true],
            'les miserables' => ['Les Misérables', 'Les Miserables', true],

            // Apostrophes (apostrophe splits words: Ocean's -> ocean + s)
            'schindlers list' => ["Schindler's List", "Schindler's List", true],
            'oceans eleven' => ["Ocean's Eleven", "Ocean's Eleven", true],

            // Long titles
            'eternal sunshine' => [
                'Eternal Sunshine of the Spotless Mind',
                'Eternal Sunshine of the Spotless Mind',
                true,
            ],
            'lord of rings' => [
                'The Lord of the Rings',
                'The Lord of the Rings',
                true,
            ],

            // Typos in real titles
            'avatar typo' => ['Avatar', 'Avater', true],
            'inception typo' => ['Inception', 'Incepton', true],
            'gladiator typo' => ['Gladiator', 'Gladiater', true],
            'braveheart typo' => ['Braveheart', 'Bravehart', true],
        ];
    }

    // ========================================================================
    // handleAnswer() tests
    // ========================================================================

    public function testHandleAnswerReturnsFalseWhenNoMatch(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix et Inception');
        $game->method('getEnglishSolution')->willReturn('The Matrix and Inception');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Avatar', null);

        self::assertFalse($result);
    }

    public function testHandleAnswerMatchesFrenchSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Le Roi Lion et Babe');
        $game->method('getEnglishSolution')->willReturn('The Lion King and Babe');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Le Roi Lion et Babe', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerMatchesEnglishSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Le Roi Lion et Babe');
        $game->method('getEnglishSolution')->willReturn('The Lion King and Babe');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'The Lion King and Babe', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerMatchesWithTypos(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Interstellar et Inception');
        $game->method('getEnglishSolution')->willReturn('Interstellar and Inception');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Intersteler et Incepton', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerMatchesWithDifferentOrder(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix et Inception');
        $game->method('getEnglishSolution')->willReturn('The Matrix and Inception');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Inception et Matrix', null);

        self::assertInstanceOf(Game::class, $result);
    }

    public function testHandleAnswerRejectsSpam(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix et Inception');
        $game->method('getEnglishSolution')->willReturn('The Matrix and Inception');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())->method('setAttempts')->with(1);
        $this->entityManager->expects(self::once())->method('flush');

        $result = $this->service->handleAnswer($game, 'Matrix Inception Avatar Titanic Alien Jaws Rocky', null);

        self::assertFalse($result);
    }

    public function testHandleAnswerIncrementsAttempts(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(5);

        $game->expects(self::once())->method('setAttempts')->with(6);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->handleAnswer($game, 'Wrong answer', null);
    }
}
