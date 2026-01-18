<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Authentication;

use App\DTO\Authentication\ForgotPasswordRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ForgotPasswordRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithEmail(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = 'user@example.com';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests email ==========

    public function testEmailRequired(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $emailErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'email'
        );

        $this->assertGreaterThan(0, $emailErrors);
        $this->assertStringContainsString('requis', array_values($emailErrors)[0]->getMessage());
    }

    public function testEmailCannotBeBlank(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testInvalidEmailFormat(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = 'invalid-email';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertStringContainsString('pas valide', $errors[0]->getMessage());
    }

    public function testValidEmailFormats(): void
    {
        $validEmails = [
            'user@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'user_123@test-domain.fr',
        ];

        foreach ($validEmails as $email) {
            $dto = new ForgotPasswordRequest();
            $dto->email = $email;

            $errors = $this->validator->validate($dto);

            $emailErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'email'
            );

            $this->assertCount(0, $emailErrors, "Email '{$email}' devrait être valide");
        }
    }

    public function testEmailMaxLength(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = str_repeat('a', 240) . '@example.com'; // > 255

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testEmailValidWithMaxLength(): void
    {
        $dto = new ForgotPasswordRequest();
        // Email de 255 caractères exactement
        $dto->email = str_repeat('a', 243) . '@example.com'; // = 255 chars

        $errors = $this->validator->validate($dto);

        // Peut avoir une erreur de format email mais pas de longueur
        $lengthErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => str_contains(strtolower($error->getMessage()), 'long')
        );

        $this->assertCount(0, $lengthErrors, "Email de 255 caractères ne devrait pas dépasser la limite");
    }

    // ========== Tests d'intégration ==========

    public function testDTOAcceptsOnlyEmail(): void
    {
        $dto = new ForgotPasswordRequest();
        $dto->email = 'test@example.com';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testMultipleInvalidEmails(): void
    {
        $invalidEmails = [
            'not-an-email',
            '@example.com',
            'user@',
            'user',
            'user@domain',
        ];

        foreach ($invalidEmails as $email) {
            $dto = new ForgotPasswordRequest();
            $dto->email = $email;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Email '{$email}' ne devrait pas être valide");
        }
    }
}
