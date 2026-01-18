<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Game;

use App\DTO\Game\GameAnswerRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GameAnswerRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithAnswer(): void
    {
        $dto = new GameAnswerRequest('Matrix et Inception');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithMinimumLength(): void
    {
        $dto = new GameAnswerRequest('12345'); // Exactement 5 caractères

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests answer ==========

    public function testAnswerRequired(): void
    {
        $dto = new GameAnswerRequest('');

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $answerErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'answer'
        );

        $this->assertGreaterThan(0, $answerErrors);
        $this->assertStringContainsString('obligatoire', array_values($answerErrors)[0]->getMessage());
    }

    public function testAnswerCannotBeBlank(): void
    {
        $dto = new GameAnswerRequest('');

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testAnswerMinLength(): void
    {
        $dto = new GameAnswerRequest('1234'); // 4 caractères < 5 min

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $answerErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'answer'
        );

        $this->assertGreaterThan(0, $answerErrors);
        $this->assertStringContainsString('au moins', array_values($answerErrors)[0]->getMessage());
    }

    public function testAnswerMaxLength(): void
    {
        $dto = new GameAnswerRequest(str_repeat('a', 256)); // > 255

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $answerErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'answer'
        );

        $this->assertGreaterThan(0, $answerErrors);
        $this->assertStringContainsString('dépasser', array_values($answerErrors)[0]->getMessage());
    }

    public function testAnswerValidWithMaxLength(): void
    {
        $dto = new GameAnswerRequest(str_repeat('a', 255)); // Exactement 255

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests formats de réponses valides ==========

    public function testValidAnswerFormats(): void
    {
        $validAnswers = [
            'Matrix et Inception',
            'Le Seigneur des Anneaux et Star Wars',
            'Pulp Fiction et Reservoir Dogs',
            'Film1 et Film2',
            'Up et Her', // Courts mais valides ensemble
            'Avatar et Titanic',
            'Interstellar et Tenet',
        ];

        foreach ($validAnswers as $answer) {
            $dto = new GameAnswerRequest($answer);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Answer '{$answer}' devrait être valide");
        }
    }

    public function testAnswerAcceptsSpecialCharacters(): void
    {
        $validAnswers = [
            'Film-1 et Film-2',
            "L'Été et L'Hiver",
            'Film: Le retour et Film 2',
            'Film (2020) et Film (2021)',
            'Été à Paris et Noël à Rome',
        ];

        foreach ($validAnswers as $answer) {
            $dto = new GameAnswerRequest($answer);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Answer with special chars '{$answer}' devrait être valide");
        }
    }

    public function testAnswerAcceptsDifferentLanguages(): void
    {
        $validAnswers = [
            'Matrix et Inception', // Anglais
            'La Vita è Bella et Amélie', // Italien/Français
            'El Camino et Breaking Bad', // Espagnol
            'Die Hard et Terminator', // Allemand/Anglais
        ];

        foreach ($validAnswers as $answer) {
            $dto = new GameAnswerRequest($answer);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Answer '{$answer}' devrait être valide");
        }
    }

    // ========== Tests cas limites ==========

    public function testAnswerWithOnlySpaces(): void
    {
        $dto = new GameAnswerRequest('     '); // 5 espaces

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testAnswerWithLeadingAndTrailingSpaces(): void
    {
        $dto = new GameAnswerRequest('  Matrix et Inception  ');

        $errors = $this->validator->validate($dto);
        // Pas d'erreur - le trim n'est pas dans la validation
        // C'est au service de gérer le trim si nécessaire
        $this->assertCount(0, $errors);
    }

    public function testAnswerWithMultipleSpaces(): void
    {
        $dto = new GameAnswerRequest('Matrix    et    Inception');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testAnswerWithNumbers(): void
    {
        $dto = new GameAnswerRequest('Film 1 et Film 2');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests d'intégration ==========

    public function testConstructorPromotionWorks(): void
    {
        $answer = 'Test et Test';
        $dto = new GameAnswerRequest($answer);

        $this->assertEquals($answer, $dto->answer);
    }

    public function testDTOStructureMatchesExpectedFormat(): void
    {
        // Simule ce que le frontend envoie
        $dto = new GameAnswerRequest('Matrix et Inception');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);

        // Vérifie que la propriété est accessible
        $this->assertNotEmpty($dto->answer);
        $this->assertEquals('Matrix et Inception', $dto->answer);
    }

    public function testMinimumValidGameAnswer(): void
    {
        // Le plus court possible : "A et B" = 6 chars, mais min est 5
        $dto = new GameAnswerRequest('12345');

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testTypicalGameAnswers(): void
    {
        $typicalAnswers = [
            'Matrix et Avatar',
            'Inception et Interstellar',
            'Star Wars et Star Trek',
            'Le Parrain et Scarface',
        ];

        foreach ($typicalAnswers as $answer) {
            $dto = new GameAnswerRequest($answer);

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Typical answer '{$answer}' devrait être valide");
        }
    }
}
