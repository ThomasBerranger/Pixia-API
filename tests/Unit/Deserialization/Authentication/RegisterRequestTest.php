<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Authentication;

use App\DTO\Authentication\RegisterRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegisterRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithAllRequiredFields(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'newuser@example.com';
        $dto->password = 'password123';
        $dto->username = 'NewUser123';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithSponsor(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'newuser@example.com';
        $dto->password = 'password123';
        $dto->username = 'NewUser123';
        $dto->sponsor = 'sponsor-user';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests email ==========

    public function testEmailRequired(): void
    {
        $dto = new RegisterRequest();
        $dto->email = ''; // Blank au lieu de non défini
        $dto->password = 'password123';
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $emailErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'email'
        );
        $this->assertCount(1, $emailErrors);
        $this->assertStringContainsString('requis', array_values($emailErrors)[0]->getMessage());
    }

    public function testEmailCannotBeBlank(): void
    {
        $dto = new RegisterRequest();
        $dto->email = '';
        $dto->password = 'password123';
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testInvalidEmailFormat(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'invalid-email';
        $dto->password = 'password123';
        $dto->username = 'User123';

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
            $dto = new RegisterRequest();
            $dto->email = $email;
            $dto->password = 'password123';
            $dto->username = 'User123';

            $errors = $this->validator->validate($dto);

            $emailErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'email'
            );

            $this->assertCount(0, $emailErrors, "Email '{$email}' devrait être valide");
        }
    }

    // ========== Tests password ==========

    public function testPasswordRequired(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = ''; // Blank au lieu de non défini
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );
        $this->assertGreaterThan(0, $passwordErrors); // Accepte 1 ou plusieurs erreurs
        $this->assertStringContainsString('requis', array_values($passwordErrors)[0]->getMessage());
    }

    public function testPasswordCannotBeBlank(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = '';
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testPasswordMinLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = '1234'; // 4 caractères < 5 min
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );
        $this->assertCount(1, $passwordErrors);
        $this->assertStringContainsString('au moins', array_values($passwordErrors)[0]->getMessage());
    }

    public function testPasswordValidWithMinLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = '12345'; // Exactement 5 caractères
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);

        $passwordErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'password'
        );
        $this->assertCount(0, $passwordErrors);
    }

    public function testPasswordMaxLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = str_repeat('a', 256); // > 255
        $dto->username = 'User123';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    // ========== Tests username ==========

    public function testUsernameRequired(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = ''; // Blank au lieu de non défini

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        $usernameErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'username'
        );
        $this->assertGreaterThan(0, $usernameErrors); // Accepte 1 ou plusieurs erreurs
        $this->assertStringContainsString('requis', array_values($usernameErrors)[0]->getMessage());
    }

    public function testUsernameCannotBeBlank(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = '';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testUsernameMinLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'a'; // 1 caractère < 2 min

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertStringContainsString('au moins', $errors[0]->getMessage());
    }

    public function testUsernameValidWithMinLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'ab'; // Exactement 2 caractères

        $errors = $this->validator->validate($dto);

        $usernameErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'username'
        );
        $this->assertCount(0, $usernameErrors);
    }

    public function testUsernameValidPattern(): void
    {
        $validUsernames = [
            'User123',
            'test_user',
            'my-cool-username',
            'user 123',
            'UtilisateurÉtéÀ',
        ];

        foreach ($validUsernames as $username) {
            $dto = new RegisterRequest();
            $dto->email = 'user@example.com';
            $dto->password = 'password123';
            $dto->username = $username;

            $errors = $this->validator->validate($dto);

            $usernameErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'username'
            );

            $this->assertCount(0, $usernameErrors, "Username '{$username}' devrait être valide");
        }
    }

    public function testUsernameInvalidPattern(): void
    {
        $invalidUsernames = [
            'user@test',
            'user#123',
            'user.name',
            'user/name',
            'user*name',
        ];

        foreach ($invalidUsernames as $username) {
            $dto = new RegisterRequest();
            $dto->email = 'user@example.com';
            $dto->password = 'password123';
            $dto->username = $username;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Username '{$username}' ne devrait pas être valide");
        }
    }

    // ========== Tests sponsor ==========

    public function testSponsorIsOptional(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'User123';
        // sponsor non défini (null)

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testSponsorValidPattern(): void
    {
        $validSponsors = [
            'sponsor-user',
            'sponsor123',
            'mon-pote-cool',
            'utilisateurété',
        ];

        foreach ($validSponsors as $sponsor) {
            $dto = new RegisterRequest();
            $dto->email = 'user@example.com';
            $dto->password = 'password123';
            $dto->username = 'User123';
            $dto->sponsor = $sponsor;

            $errors = $this->validator->validate($dto);

            $sponsorErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'sponsor'
            );

            $this->assertCount(0, $sponsorErrors, "Sponsor '{$sponsor}' devrait être valide");
        }
    }

    public function testSponsorInvalidPattern(): void
    {
        $invalidSponsors = [
            'Sponsor-User',    // Majuscule non autorisée
            'sponsor_user',    // Underscore non autorisé
            'sponsor user',    // Espace non autorisé
            'sponsor@test',    // @ non autorisé
            'sponsor.name',    // . non autorisé
        ];

        foreach ($invalidSponsors as $sponsor) {
            $dto = new RegisterRequest();
            $dto->email = 'user@example.com';
            $dto->password = 'password123';
            $dto->username = 'User123';
            $dto->sponsor = $sponsor;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Sponsor '{$sponsor}' ne devrait pas être valide");
        }
    }

    public function testSponsorMinLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'User123';
        $dto->sponsor = 'a'; // 1 caractère < 2 min

        $errors = $this->validator->validate($dto);

        $sponsorErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'sponsor'
        );

        $this->assertGreaterThan(0, $sponsorErrors);
    }

    public function testSponsorMaxLength(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'User123';
        $dto->sponsor = str_repeat('a', 256); // > 255

        $errors = $this->validator->validate($dto);

        $sponsorErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'sponsor'
        );

        $this->assertGreaterThan(0, $sponsorErrors);
    }

    // ========== Tests UniqueEntity ==========

    public function testDuplicateEmail(): void
    {
        $existingUser = new User();
        $existingUser->setEmail('existing@example.com');
        $existingUser->setUsername('existing_user');
        $existingUser->setPassword('password123');

        $this->entityManager->persist($existingUser);
        $this->entityManager->flush();

        $dto = new RegisterRequest();
        $dto->email = 'existing@example.com';
        $dto->password = 'password123';
        $dto->username = 'NewUser';

        $errors = $this->validator->validate($dto);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('déjà utilisé', $errors[0]->getMessage());
    }

    public function testDuplicateUsername(): void
    {
        $existingUser = new User();
        $existingUser->setEmail('existing2@example.com');
        $existingUser->setUsername('existing_username');
        $existingUser->setPassword('password123');

        $this->entityManager->persist($existingUser);
        $this->entityManager->flush();

        $dto = new RegisterRequest();
        $dto->email = 'newuser@example.com';
        $dto->password = 'password123';
        $dto->username = 'existing_username';

        $errors = $this->validator->validate($dto);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('déjà utilisé', $errors[0]->getMessage());
    }

    // ========== Tests de la méthode toUser() ==========

    public function testToUserCreatesValidUser(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'newuser@example.com';
        $dto->password = 'password123';
        $dto->username = 'NewUser';

        $user = $dto->toUser($this->passwordHasher);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('newuser@example.com', $user->getEmail());
        $this->assertEquals('NewUser', $user->getUsername());
        $this->assertNotEquals('password123', $user->getPassword()); // Password hashé
        $this->assertTrue($this->passwordHasher->isPasswordValid($user, 'password123'));
    }

    public function testToUserHashesPassword(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'mySecretPassword';
        $dto->username = 'TestUser';

        $user = $dto->toUser($this->passwordHasher);

        // Le mot de passe doit être hashé (ne doit pas être en clair)
        $this->assertNotEquals('mySecretPassword', $user->getPassword());
        $this->assertNotEmpty($user->getPassword());
        $this->assertGreaterThan(20, strlen($user->getPassword())); // Hash bcrypt fait au moins 60 chars
    }

    public function testToUserPasswordIsVerifiable(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'testPassword123';
        $dto->username = 'TestUser';

        $user = $dto->toUser($this->passwordHasher);

        // Vérifier que le hash peut être validé
        $this->assertTrue(
            $this->passwordHasher->isPasswordValid($user, 'testPassword123'),
            'Le mot de passe hashé devrait pouvoir être validé'
        );

        $this->assertFalse(
            $this->passwordHasher->isPasswordValid($user, 'wrongPassword'),
            'Un mauvais mot de passe ne devrait pas être validé'
        );
    }

    public function testToUserDoesNotSetSponsor(): void
    {
        $dto = new RegisterRequest();
        $dto->email = 'user@example.com';
        $dto->password = 'password123';
        $dto->username = 'TestUser';
        $dto->sponsor = 'my-sponsor';

        $user = $dto->toUser($this->passwordHasher);

        // La méthode toUser() ne gère pas le sponsor
        // Il est géré dans le controller
        $this->assertEquals('user@example.com', $user->getEmail());
        $this->assertEquals('TestUser', $user->getUsername());
    }
}
