<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Game;

use App\DTO\Game\GameShowRequest;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GameShowRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithDateAndType(): void
    {
        $dto = new GameShowRequest('2024-01-15', GameType::MULTIVERS);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithOnlyDate(): void
    {
        $dto = new GameShowRequest('2024-01-15', null);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithNullValues(): void
    {
        $dto = new GameShowRequest(null, null);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests date ==========

    public function testDateIsOptional(): void
    {
        $dto = new GameShowRequest(null, GameType::MULTIVERS);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testDateValidFormat(): void
    {
        $validDates = [
            '2024-01-01',
            '2023-12-31',
            '2025-06-15',
            '2024-02-29', // Année bissextile
        ];

        foreach ($validDates as $date) {
            $dto = new GameShowRequest($date, null);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Date '{$date}' devrait être valide");
        }
    }

    public function testDateInvalidFormat(): void
    {
        $invalidDates = [
            '2024/01/15',     // Slashes au lieu de tirets
            '15-01-2024',     // Format DD-MM-YYYY
            '2024-1-15',      // Mois sans zéro
            '2024-01-5',      // Jour sans zéro
            '01-15-2024',     // Format MM-DD-YYYY
            'invalid',        // Texte
            '2024-13-01',     // Mois invalide
            '2024-02-30',     // Jour invalide
        ];

        foreach ($invalidDates as $date) {
            $dto = new GameShowRequest($date, null);

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Date '{$date}' ne devrait pas être valide");
        }
    }

    public function testDateFormatMessage(): void
    {
        $dto = new GameShowRequest('invalid-date', null);

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertStringContainsString('YYYY-MM-DD', $errors[0]->getMessage());
    }

    // ========== Tests type (Enum) ==========

    public function testTypeIsOptional(): void
    {
        $dto = new GameShowRequest('2024-01-15', null);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testTypeAcceptsValidEnum(): void
    {
        // Test tous les cas de l'enum GameType
        $validTypes = GameType::cases();

        foreach ($validTypes as $type) {
            $dto = new GameShowRequest('2024-01-15', $type);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Type '{$type->value}' devrait être valide");
        }
    }

    // ========== Tests méthode getDate() ==========

    public function testGetDateReturnsDateTimeImmutable(): void
    {
        $dto = new GameShowRequest('2024-01-15', null);

        $date = $dto->getDate();

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
    }

    public function testGetDateReturnsCorrectDate(): void
    {
        $dto = new GameShowRequest('2024-01-15', null);

        $date = $dto->getDate();

        $this->assertEquals('2024-01-15', $date->format('Y-m-d'));
    }

    public function testGetDateReturnsTodayWhenNull(): void
    {
        $dto = new GameShowRequest(null, null);

        $date = $dto->getDate();

        $today = new \DateTimeImmutable('today');
        $this->assertEquals($today->format('Y-m-d'), $date->format('Y-m-d'));
    }

    public function testGetDateReturnsTodayWhenInvalidFormat(): void
    {
        $dto = new GameShowRequest('invalid-date', null);

        $date = $dto->getDate();

        $today = new \DateTimeImmutable('today');
        $this->assertEquals($today->format('Y-m-d'), $date->format('Y-m-d'));
    }

    public function testGetDateWithDifferentDates(): void
    {
        $dates = [
            '2024-01-01',
            '2023-12-31',
            '2025-06-15',
        ];

        foreach ($dates as $dateString) {
            $dto = new GameShowRequest($dateString, null);
            $date = $dto->getDate();

            $this->assertEquals($dateString, $date->format('Y-m-d'));
        }
    }

    // ========== Tests méthode getType() ==========

    public function testGetTypeReturnsGameType(): void
    {
        $dto = new GameShowRequest(null, GameType::MULTIVERS);

        $type = $dto->getType();

        $this->assertInstanceOf(GameType::class, $type);
    }

    public function testGetTypeReturnsProvidedType(): void
    {
        $dto = new GameShowRequest(null, GameType::MULTIVERS);

        $type = $dto->getType();

        $this->assertEquals(GameType::MULTIVERS, $type);
    }

    public function testGetTypeReturnsDefaultWhenNull(): void
    {
        $dto = new GameShowRequest(null, null);

        $type = $dto->getType();

        $this->assertEquals(GameType::MULTIVERS, $type);
    }

    public function testGetTypeWithDifferentTypes(): void
    {
        $types = GameType::cases();

        foreach ($types as $gameType) {
            $dto = new GameShowRequest(null, $gameType);
            $type = $dto->getType();

            $this->assertEquals($gameType, $type);
        }
    }

    // ========== Tests d'intégration ==========

    public function testConstructorPromotionWorks(): void
    {
        $date = '2024-01-15';
        $type = GameType::MULTIVERS;

        $dto = new GameShowRequest($date, $type);

        $this->assertEquals($date, $dto->date);
        $this->assertEquals($type, $dto->type);
    }

    public function testDTOWithBothNullReturnsDefaults(): void
    {
        $dto = new GameShowRequest(null, null);

        $date = $dto->getDate();
        $type = $dto->getType();

        // Date devrait être aujourd'hui
        $this->assertEquals(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            $date->format('Y-m-d')
        );

        // Type devrait être MULTIVERS par défaut
        $this->assertEquals(GameType::MULTIVERS, $type);
    }

    public function testDTOStructureMatchesExpectedFormat(): void
    {
        // Simule ce que le frontend envoie en query string
        $dto = new GameShowRequest('2024-01-15', GameType::MULTIVERS);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);

        // Vérifie que les méthodes retournent les bonnes valeurs
        $this->assertEquals('2024-01-15', $dto->getDate()->format('Y-m-d'));
        $this->assertEquals(GameType::MULTIVERS, $dto->getType());
    }

    public function testTypicalUsageScenarios(): void
    {
        // Scénario 1 : Date spécifique, type par défaut
        $dto1 = new GameShowRequest('2024-01-15', null);
        $this->assertEquals('2024-01-15', $dto1->getDate()->format('Y-m-d'));
        $this->assertEquals(GameType::MULTIVERS, $dto1->getType());

        // Scénario 2 : Aujourd'hui, type spécifique
        $dto2 = new GameShowRequest(null, GameType::MULTIVERS);
        $this->assertEquals(
            (new \DateTimeImmutable('today'))->format('Y-m-d'),
            $dto2->getDate()->format('Y-m-d')
        );

        // Scénario 3 : Tout par défaut
        $dto3 = new GameShowRequest(null, null);
        $this->assertInstanceOf(\DateTimeImmutable::class, $dto3->getDate());
        $this->assertEquals(GameType::MULTIVERS, $dto3->getType());
    }
}
