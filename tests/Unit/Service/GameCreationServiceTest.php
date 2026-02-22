<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Game;
use App\Enum\GameType;
use App\Repository\GameRepository;
use App\Service\Game\GameCreationService;
use App\Service\Game\GameSolutionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class GameCreationServiceTest extends TestCase
{
    private GameCreationService $service;
    private EntityManagerInterface $entityManager;
    private GameSolutionService $gameSolutionService;
    private GameRepository $gameRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->gameSolutionService = $this->createMock(GameSolutionService::class);
        $this->gameRepository = $this->createMock(GameRepository::class);

        $this->service = new GameCreationService(
            $this->entityManager,
            $this->gameSolutionService,
            $this->gameRepository
        );
    }

    // ========== Tests gameSolutionExist() - Cas false ==========

    public function testGameSolutionExistReturnsFalseWhenNoGamesExist(): void
    {
        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->with(['solution'])
            ->willReturn([]);

        $result = $this->service->gameSolutionExist('Matrix et Inception');

        $this->assertFalse($result);
    }

    public function testGameSolutionExistReturnsFalseWhenNoMatchFound(): void
    {
        $game1 = $this->createMock(Game::class);
        $game1->method('getSolution')->willReturn('Avatar et Titanic');

        $game2 = $this->createMock(Game::class);
        $game2->method('getSolution')->willReturn('Interstellar et Tenet');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->with(['solution'])
            ->willReturn([$game1, $game2]);

        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturn(false);

        $result = $this->service->gameSolutionExist('Matrix et Inception');

        $this->assertFalse($result);
    }

    // ========== Tests gameSolutionExist() - Cas string ==========

    public function testGameSolutionExistReturnsExistingSolutionWhenMatchFound(): void
    {
        $existingGame = $this->createMock(Game::class);
        $existingGame->method('getSolution')->willReturn('Matrix et Inception');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->with(['solution'])
            ->willReturn([$existingGame]);

        $this->gameSolutionService
            ->method('wordsMatch')
            ->with('Matrix et Inception', 'Matrix et Inception')
            ->willReturn(true);

        $result = $this->service->gameSolutionExist('Matrix et Inception');

        $this->assertSame('Matrix et Inception', $result);
    }

    public function testGameSolutionExistReturnsFirstMatchingGame(): void
    {
        $game1 = $this->createMock(Game::class);
        $game1->method('getSolution')->willReturn('Matrix et Inception');

        $game2 = $this->createMock(Game::class);
        $game2->method('getSolution')->willReturn('Matrix et Avatar');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->with(['solution'])
            ->willReturn([$game1, $game2]);

        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturnCallback(function ($existing, $tested) {
                return $existing === 'Matrix et Inception';
            });

        $result = $this->service->gameSolutionExist('Matrix et Inception');

        // Doit retourner la première solution qui match
        $this->assertSame('Matrix et Inception', $result);
    }

    // ========== Tests avec wordsMatch() ==========

    public function testGameSolutionExistUsesWordsMatchForComparison(): void
    {
        $existingGame = $this->createMock(Game::class);
        $existingGame->method('getSolution')->willReturn('Matrix et Inception');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$existingGame]);

        // Simule un match avec typo/accents
        $this->gameSolutionService
            ->expects($this->once())
            ->method('wordsMatch')
            ->with('Matrix et Inception', 'Matix et Incepton')
            ->willReturn(true);

        $result = $this->service->gameSolutionExist('Matix et Incepton');

        $this->assertSame('Matrix et Inception', $result);
    }

    public function testGameSolutionExistCallsWordsMatchForEachGame(): void
    {
        $game1 = $this->createMock(Game::class);
        $game1->method('getSolution')->willReturn('Avatar et Titanic');

        $game2 = $this->createMock(Game::class);
        $game2->method('getSolution')->willReturn('Matrix et Inception');

        $game3 = $this->createMock(Game::class);
        $game3->method('getSolution')->willReturn('Interstellar et Tenet');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$game1, $game2, $game3]);

        $callCount = 0;
        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturnCallback(function ($existing, $tested) use (&$callCount) {
                $callCount++;
                return $existing === 'Matrix et Inception';
            });

        $result = $this->service->gameSolutionExist('Matrix et Inception');

        // Devrait s'arrêter après avoir trouvé le match (game2)
        $this->assertEquals(2, $callCount);
        $this->assertSame('Matrix et Inception', $result);
    }

    // ========== Tests d'intégration ==========

    public function testGameSolutionExistQueriesOnlySolutionField(): void
    {
        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->expects($this->once())
            ->method('findByFields')
            ->with(['solution'])
            ->willReturn([]);

        $this->service->gameSolutionExist('Test Solution');
    }

    public function testGameSolutionExistWithMultipleGamesNoMatch(): void
    {
        $games = [];
        for ($i = 1; $i <= 5; $i++) {
            $game = $this->createMock(Game::class);
            $game->method('getSolution')->willReturn("Solution $i");
            $games[] = $game;
        }

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn($games);

        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturn(false);

        $result = $this->service->gameSolutionExist('Different Solution');

        $this->assertFalse($result);
    }

    public function testGameSolutionExistWithCaseInsensitiveMatch(): void
    {
        $existingGame = $this->createMock(Game::class);
        $existingGame->method('getSolution')->willReturn('Matrix et Inception');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$existingGame]);

        // wordsMatch gère la casse
        $this->gameSolutionService
            ->method('wordsMatch')
            ->with('Matrix et Inception', 'MATRIX ET INCEPTION')
            ->willReturn(true);

        $result = $this->service->gameSolutionExist('MATRIX ET INCEPTION');

        $this->assertSame('Matrix et Inception', $result);
    }

    public function testGameSolutionExistWithAccentsMatch(): void
    {
        $existingGame = $this->createMock(Game::class);
        $existingGame->method('getSolution')->willReturn('Le Fabuleux Destin');

        $this->entityManager
            ->method('getRepository')
            ->with(Game::class)
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$existingGame]);

        // wordsMatch gère les accents
        $this->gameSolutionService
            ->method('wordsMatch')
            ->with('Le Fabuleux Destin', 'Le Fäbuleûx Dèstïn')
            ->willReturn(true);

        $result = $this->service->gameSolutionExist('Le Fäbuleûx Dèstïn');

        $this->assertSame('Le Fabuleux Destin', $result);
    }

    // ========== Tests return type ==========

    public function testGameSolutionExistReturnTypeIsStringWhenFound(): void
    {
        $existingGame = $this->createMock(Game::class);
        $existingGame->method('getSolution')->willReturn('Test Solution');

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$existingGame]);

        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturn(true);

        $result = $this->service->gameSolutionExist('Test Solution');

        $this->assertIsString($result);
    }

    public function testGameSolutionExistReturnTypeIsBoolWhenNotFound(): void
    {
        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([]);

        $result = $this->service->gameSolutionExist('Test Solution');

        $this->assertIsBool($result);
        $this->assertFalse($result);
    }

    public function testGameSolutionExistStopsAtFirstMatch(): void
    {
        $game1 = $this->createMock(Game::class);
        $game1->method('getSolution')->willReturn('Solution 1');

        $game2 = $this->createMock(Game::class);
        $game2->method('getSolution')->willReturn('Solution 2');

        $game3 = $this->createMock(Game::class);
        $game3->method('getSolution')->willReturn('Solution 3');

        $this->entityManager
            ->method('getRepository')
            ->willReturn($this->gameRepository);

        $this->gameRepository
            ->method('findByFields')
            ->willReturn([$game1, $game2, $game3]);

        $callCount = 0;
        $this->gameSolutionService
            ->method('wordsMatch')
            ->willReturnCallback(function () use (&$callCount) {
                $callCount++;
                return $callCount === 2; // Match au 2ème appel
            });

        $result = $this->service->gameSolutionExist('Test');

        // Devrait s'arrêter après le 2ème, pas vérifier le 3ème
        $this->assertEquals(2, $callCount);
        $this->assertSame('Solution 2', $result);
    }

    // ========== Tests getNextAvailableDate() ==========

    public function testGetNextAvailableDateReturnsOneWeekWhenNoGamesExist(): void
    {
        // Arrange
        $type = GameType::MULTIVERS;
        $expectedDate = (new DateTimeImmutable('+1 week'))->setTime(0, 0);

        $this->mockQueryBuilder([]);

        // Act
        $result = $this->service->getNextAvailableDate($type);

        // Assert
        $this->assertEquals($expectedDate->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetNextAvailableDateSkipsApprovedGameAtOneWeek(): void
    {
        // Arrange
        $type = GameType::MULTIVERS;
        $oneWeek = (new DateTimeImmutable('+1 week'))->setTime(0, 0);
        $expectedDate = $oneWeek->modify('+1 day');

        // Game APPROVED à +1 semaine (collision)
        $this->mockQueryBuilder([$oneWeek]);

        // Act
        $result = $this->service->getNextAvailableDate($type);

        // Assert
        $this->assertEquals($expectedDate->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetNextAvailableDateSkipsMultipleConsecutiveDays(): void
    {
        // Arrange
        $type = GameType::MULTIVERS;
        $oneWeek = (new DateTimeImmutable('+1 week'))->setTime(0, 0);

        // Games APPROVED à +7, +8, +9 jours (collisions consécutives)
        $takenDates = [
            $oneWeek,
            $oneWeek->modify('+1 day'),
            $oneWeek->modify('+2 days'),
        ];

        $expectedDate = $oneWeek->modify('+3 days'); // +10 jours total

        $this->mockQueryBuilder($takenDates);

        // Act
        $result = $this->service->getNextAvailableDate($type);

        // Assert
        $this->assertEquals($expectedDate->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetNextAvailableDateFindsGapBetweenApprovedGames(): void
    {
        // Arrange
        $type = GameType::MULTIVERS;
        $oneWeek = (new DateTimeImmutable('+1 week'))->setTime(0, 0);

        // Games APPROVED à +7 et +9 jours (gap à +8)
        $takenDates = [
            $oneWeek,
            $oneWeek->modify('+2 days'),
        ];

        $expectedDate = $oneWeek->modify('+1 day'); // Le gap

        $this->mockQueryBuilder($takenDates);

        // Act
        $result = $this->service->getNextAvailableDate($type);

        // Assert
        $this->assertEquals($expectedDate->format('Y-m-d'), $result->format('Y-m-d'));
    }

    public function testGetNextAvailableDateIgnoresDifferentGameType(): void
    {
        // Arrange
        $type = GameType::MULTIVERS;
        $oneWeek = (new DateTimeImmutable('+1 week'))->setTime(0, 0);

        // Aucun game APPROVED du type MULTIVERS (query retourne vide car filtrée par type)
        $this->mockQueryBuilder([]);

        // Act
        $result = $this->service->getNextAvailableDate($type);

        // Assert
        // Doit retourner +1 semaine car pas de collision pour ce type
        $this->assertEquals($oneWeek->format('Y-m-d'), $result->format('Y-m-d'));
    }

    /**
     * Mock le QueryBuilder pour retourner des dates prises
     * @param DateTimeImmutable[] $takenDates
     */
    private function mockQueryBuilder(array $takenDates): void
    {
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $query = $this->createMock(Query::class);

        $this->gameRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('g')
            ->willReturn($queryBuilder);

        $queryBuilder->expects($this->once())
            ->method('select')
            ->with('g.date')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();

        $queryBuilder->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnSelf();

        $queryBuilder->expects($this->exactly(3))
            ->method('setParameter')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('orderBy')
            ->with('g.date', 'ASC')
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->with(90)
            ->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $query->expects($this->once())
            ->method('getSingleColumnResult')
            ->willReturn($takenDates);
    }
}
