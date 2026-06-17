<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Game;

use App\DTO\Game\GameShowRequest;
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

    public function testValidDataWithDate(): void
    {
        $dto = new GameShowRequest('2024-01-15');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithNullDate(): void
    {
        $dto = new GameShowRequest(null);

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests date ==========

    public function testDateIsOptional(): void
    {
        $dto = new GameShowRequest(null);

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
            $dto = new GameShowRequest($date);

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
            $dto = new GameShowRequest($date);

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Date '{$date}' ne devrait pas être valide");
        }
    }

    public function testDateFormatMessage(): void
    {
        $dto = new GameShowRequest('invalid-date');

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertStringContainsString('YYYY-MM-DD', $errors[0]->getMessage());
    }

    // ========== Tests méthode getDate() ==========

    public function testGetDateReturnsDateTimeImmutable(): void
    {
        $dto = new GameShowRequest('2024-01-15');

        $date = $dto->getDate();

        $this->assertInstanceOf(\DateTimeImmutable::class, $date);
    }

    public function testGetDateReturnsCorrectDate(): void
    {
        $dto = new GameShowRequest('2024-01-15');

        $date = $dto->getDate();

        $this->assertEquals('2024-01-15', $date->format('Y-m-d'));
    }

    public function testGetDateReturnsTodayWhenNull(): void
    {
        $dto = new GameShowRequest(null);

        $date = $dto->getDate();

        $today = new \DateTimeImmutable('today');
        $this->assertEquals($today->format('Y-m-d'), $date->format('Y-m-d'));
    }

    public function testGetDateReturnsTodayWhenInvalidFormat(): void
    {
        $dto = new GameShowRequest('invalid-date');

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
            $dto = new GameShowRequest($dateString);
            $date = $dto->getDate();

            $this->assertEquals($dateString, $date->format('Y-m-d'));
        }
    }

    // ========== Tests d'intégration ==========

    public function testConstructorPromotionWorks(): void
    {
        $date = '2024-01-15';

        $dto = new GameShowRequest($date);

        $this->assertEquals($date, $dto->date);
    }
}
