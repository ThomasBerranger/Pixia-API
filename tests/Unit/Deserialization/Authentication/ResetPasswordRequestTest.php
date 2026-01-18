<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Authentication;

use App\DTO\Authentication\ResetPasswordRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ResetPasswordRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithTokenAndPassword(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-reset-token-1234567890';
        $dto->password = 'newPassword123';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests token ==========

    public function testTokenRequired(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = '';
        $dto->password = 'password123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $tokenErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'token'
        );

        $this->assertGreaterThan(0, $tokenErrors);
        $this->assertStringContainsString('requis', array_values($tokenErrors)[0]->getMessage());
    }

    public function testTokenCannotBeBlank(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = '';
        $dto->password = 'password123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testTokenAcceptsAnyNonEmptyString(): void
    {
        $validTokens = [
            'short',
            'very-long-token-with-dashes-and-numbers-123456789',
            'TOKEN_WITH_UNDERSCORES',
            'abc123',
        ];

        foreach ($validTokens as $token) {
            $dto = new ResetPasswordRequest();
            $dto->token = $token;
            $dto->password = 'password123';

            $errors = $this->validator->validate($dto);

            $tokenErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'token'
            );

            $this->assertCount(0, $tokenErrors, "Token '{$token}' devrait être valide");
        }
    }

    // ========== Tests password ==========

    public function testPasswordRequired(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );

        $this->assertGreaterThan(0, $passwordErrors);
        $this->assertStringContainsString('requis', array_values($passwordErrors)[0]->getMessage());
    }

    public function testPasswordCannotBeBlank(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testPasswordMinLength(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = '1234'; // 4 caractères < 5 min

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );

        $this->assertGreaterThan(0, $passwordErrors);
        $this->assertStringContainsString('au moins', array_values($passwordErrors)[0]->getMessage());
    }

    public function testPasswordValidWithMinLength(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = '12345'; // Exactement 5 caractères

        $errors = $this->validator->validate($dto);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );

        $this->assertCount(0, $passwordErrors);
    }

    public function testPasswordMaxLength(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = str_repeat('a', 256); // > 255

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testPasswordValidWithMaxLength(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = 'valid-token';
        $dto->password = str_repeat('a', 255); // Exactement 255

        $errors = $this->validator->validate($dto);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );

        $this->assertCount(0, $passwordErrors);
    }

    // ========== Tests d'intégration ==========

    public function testBothFieldsRequired(): void
    {
        $dto = new ResetPasswordRequest();
        $dto->token = '';
        $dto->password = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThanOrEqual(2, count($errors), 'Devrait avoir au moins 2 erreurs (token + password)');
    }

    public function testValidPasswordFormats(): void
    {
        $validPasswords = [
            'simple',
            'Complex123!',
            'with spaces allowed',
            'àccénts-õk',
        ];

        foreach ($validPasswords as $password) {
            $dto = new ResetPasswordRequest();
            $dto->token = 'valid-token';
            $dto->password = $password;

            $errors = $this->validator->validate($dto);

            $passwordErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'password'
            );

            $this->assertCount(0, $passwordErrors, "Password '{$password}' devrait être valide");
        }
    }

    public function testDTOStructureMatchesExpectedFormat(): void
    {
        // Simule ce que le frontend envoie
        $dto = new ResetPasswordRequest();
        $dto->token = 'abcd1234-reset-token-from-email';
        $dto->password = 'myNewSecurePassword123';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }
}
