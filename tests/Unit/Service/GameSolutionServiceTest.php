<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
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

    public function testWordsMatchIgnoresStopWords(): void
    {
        $result = $this->service->wordsMatch('Le Seigneur des Anneaux', 'Seigneur Anneaux');

        self::assertTrue($result);
    }

    public function testWordsMatchFiltersShortWords(): void
    {
        $result = $this->service->wordsMatch('The Lord of the Rings', 'Lord Rings');

        self::assertTrue($result);
    }

    public function testWordsMatchWithSpecialCharacters(): void
    {
        $result = $this->service->wordsMatch('Spider-Man', 'Spider Man');

        self::assertTrue($result);
    }

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

    public function testWordsMatchWith80PercentThreshold(): void
    {
        $result = $this->service->wordsMatch('Star Wars Episode Four', 'Star Wars Episode');

        self::assertFalse($result);
    }

    public function testWordsMatchExactly80Percent(): void
    {
        $result = $this->service->wordsMatch('Harry Potter Chamber Secrets Five', 'Harry Potter Chamber Secrets');

        self::assertTrue($result);
    }

    // ========== Tests handleAnswer() ==========

    public function testHandleAnswerReturnsFalseWhenNoMatch(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
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

    public function testHandleAnswerMatchesSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Retour vers le futur');
        $game->method('getEnglishSolution')->willReturn('Back to the Future');
        $game->method('getAttempts')->willReturn(2);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(3);

        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->handleAnswer($game, 'Retour vers le futur', $player);

        self::assertSame($game, $result);
    }

    public function testHandleAnswerMatchesEnglishSolution(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Retour vers le futur');
        $game->method('getEnglishSolution')->willReturn('Back to the Future');
        $game->method('getAttempts')->willReturn(3);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(4);

        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->handleAnswer($game, 'Back to the Future', $player);

        self::assertSame($game, $result);
    }

    public function testHandleAnswerReturnsGameWhenMatchWithoutPlayer(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(1);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->handleAnswer($game, 'Matrix', null);

        self::assertSame($game, $result);
    }

    public function testHandleAnswerCallsHandleWinWhenMatchWithPlayer(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(1);

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

        $result = $this->service->handleAnswer($game, 'Matrix', $player);

        self::assertSame($game, $result);
    }

    public function testHandleAnswerDoesNotCallHandleWinWhenAlreadyWon(): void
    {
        $game = $this->createMock(Game::class);
        $game->method('getSolution')->willReturn('Matrix');
        $game->method('getEnglishSolution')->willReturn('The Matrix');
        $game->method('getAttempts')->willReturn(0);

        $game->expects(self::once())
            ->method('setAttempts')
            ->with(1);

        $player = $this->createMock(User::class);

        $this->achievementRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['player' => $player, 'game' => $game])
            ->willReturn(new Achievement());

        $this->achievementService->expects(self::never())->method('saveResult');
        $this->userService->expects(self::never())->method('addGamePoints');

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->service->handleAnswer($game, 'Matrix', $player);

        self::assertSame($game, $result);
    }
}
