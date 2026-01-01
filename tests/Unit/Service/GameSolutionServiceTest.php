<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\AchievementRepository;
use App\Service\AchievementService;
use App\Service\GameSolutionService;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
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

    // ========== Tests wordsMatch() ==========

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

    // note ici
    public function testWordsMatchIgnoresStopWords(): void
    {
        // Les stopwords (le, la, de, etc.) sont filtrés
        $result = $this->service->wordsMatch('Le Seigneur des Anneaux', 'Seigneur Anneaux');

        self::assertTrue($result);
    }

    public function testWordsMatchWithTypo(): void
    {
        // Levenshtein distance permet une tolérance sur les fautes de frappe
        $result = $this->service->wordsMatch('Matrix', 'Matix');

        self::assertTrue($result);
    }

    public function testWordsMatchWithMultipleTypos(): void
    {
        // Trop de différences = pas de match
        $result = $this->service->wordsMatch('Matrix', 'Mtx');

        self::assertFalse($result);
    }

    public function testWordsMatchWith80PercentThreshold(): void
    {
        // 4 mots attendus, 3 matchent = 75% < 80% → false
        $result = $this->service->wordsMatch('Star Wars Episode Four', 'Star Wars Episode');

        self::assertFalse($result);
    }

    public function testWordsMatchExactly80Percent(): void
    {
        // 5 mots, 4 matchent = 80% → true
        $result = $this->service->wordsMatch('Harry Potter Chamber Secrets Five', 'Harry Potter Chamber Secrets');

        self::assertTrue($result);
    }

    // note ici
    public function testWordsMatchFiltersShortWords(): void
    {
        // Les mots de <= 2 caractères sont ignorés
        $result = $this->service->wordsMatch('The Lord of the Rings', 'Lord Rings');

        self::assertTrue($result);
    }

    public function testWordsMatchWithSpecialCharacters(): void
    {
        // Les caractères spéciaux sont retirés par la regex [a-z]+
        $result = $this->service->wordsMatch('Spider-Man', 'Spider Man');

        self::assertTrue($result);
    }

    // ========== Tests save() ==========

    public function testSaveReturnsFalseWhenNoMatch(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');

        $result = $this->service->save($game, 'Avatar', null);

        self::assertFalse($result);
    }

    public function testSaveReturnsGameWhenMatchWithoutPlayer(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');

        $result = $this->service->save($game, 'Matrix', null);

        self::assertSame($game, $result);
    }

    public function testSaveCallsHandleWinWhenMatchWithPlayer(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');

        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['player' => $player, 'game' => $game])
            ->willReturn(null);

        $this->achievementService
            ->expects(self::once())
            ->method('saveResult')
            ->with($player, $game);

        $this->userService
            ->expects(self::once())
            ->method('addGamePoints')
            ->with($player, $game);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->save($game, 'Matrix', $player);

        self::assertSame($game, $result);
    }

    public function testSaveMatchesEnglishSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Retour vers le futur');
        $game->method('getEnglishSolution')->willReturn('Back to the Future');

        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('findOneBy')
            ->willReturn(null);

        $result = $this->service->save($game, 'Back to the Future', $player);

        self::assertSame($game, $result);
    }

    // ========== Tests handleWin() ==========

    public function testHandleWinDoesNothingWhenAchievementExists(): void
    {
        $game = $this->createMock(Game::class);
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['player' => $player, 'game' => $game])
            ->willReturn(new \stdClass());

        $this->achievementService->expects(self::never())->method('saveResult');
        $this->userService->expects(self::never())->method('addGamePoints');
        $this->entityManager->expects(self::never())->method('flush');

        $this->service->handleWin($game, $player);
    }

    public function testHandleWinSavesNewAchievement(): void
    {
        $game = $this->createMock(Game::class);
        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['player' => $player, 'game' => $game])
            ->willReturn(null);

        $this->achievementService
            ->expects(self::once())
            ->method('saveResult')
            ->with($player, $game);

        $this->userService
            ->expects(self::once())
            ->method('addGamePoints')
            ->with($player, $game);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $this->service->handleWin($game, $player);
    }
}