<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\User;

use App\DTO\User\UpdateRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UpdateRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    // ========== Tests de validation ==========

    public function testValidDataWithAllFields(): void
    {
        $dto = new UpdateRequest();
        $dto->email = 'valid@example.com';
        $dto->username = 'ValidUser123';
        $dto->avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?seed=test';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithNullFields(): void
    {
        $dto = new UpdateRequest();

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithOnlyEmail(): void
    {
        $dto = new UpdateRequest();
        $dto->email = 'test@example.com';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests email ==========

    public function testInvalidEmail(): void
    {
        $dto = new UpdateRequest();
        $dto->email = 'invalid-email';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertEquals('email', $errors[0]->getPropertyPath());
        $this->assertStringContainsString('pas valide', $errors[0]->getMessage());
    }

    public function testInvalidEmailFormat(): void
    {
        $dto = new UpdateRequest();
        $dto->email = 'test@';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    // ========== Tests username ==========

    public function testUsernameMinLength(): void
    {
        $dto = new UpdateRequest();
        $dto->username = 'a';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
        $this->assertStringContainsString('au moins', $errors[0]->getMessage());
    }

    public function testUsernameValidWithMinLength(): void
    {
        $dto = new UpdateRequest();
        $dto->username = 'ab';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testUsernameValidPattern(): void
    {
        $validUsernames = [
            'User123',
            'test_user_with_',
            'test-user-with-',
            'user 123',
            'UtilisateurÉtéÀ',
        ];

        foreach ($validUsernames as $username) {
            $dto = new UpdateRequest();
            $dto->username = $username;

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors, "Username '{$username}' devrait être valide");
        }
    }

    public function testUsernameInvalidPattern(): void
    {
        $invalidUsernames = [
            'user@test',    // @ non autorisé
            'user#123',     // # non autorisé
            'user.name',    // . non autorisé
            'user/name',    // / non autorisé
            'user*name',    // * non autorisé
        ];

        foreach ($invalidUsernames as $username) {
            $dto = new UpdateRequest();
            $dto->username = $username;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors);
        }
    }

    // ========== Tests avatarUrl ==========

    public function testAvatarUrlMustBeHttps(): void
    {
        $dto = new UpdateRequest();
        $dto->avatarUrl = 'http://api.dicebear.com/9.x/avataaars/svg?seed=test';

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testAvatarUrlValidPattern(): void
    {
        $validUrls = [
            'https://api.dicebear.com/9.x/avataaars/svg?seed=test',
            'https://api.dicebear.com/10.x/personas/svg?seed=user',
            'https://api.dicebear.com/7.x/bottts/svg?backgroundColor=blue',
        ];

        foreach ($validUrls as $url) {
            $dto = new UpdateRequest();
            $dto->avatarUrl = $url;

            $errors = $this->validator->validate($dto);
            $this->assertCount(0, $errors);
        }
    }

    public function testAvatarUrlInvalidPattern(): void
    {
        $invalidUrls = [
            'https://example.com/avatar.png',                   // Pas dicebear
            'https://api.dicebear.com/avatar.svg',              // Pattern incomplet
            'https://dicebear.com/9.x/avataaars/svg?seed=test', // Manque api.
        ];

        foreach ($invalidUrls as $url) {
            $dto = new UpdateRequest();
            $dto->avatarUrl = $url;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors);
        }
    }

    public function testAvatarUrlMaxLength(): void
    {
        $dto = new UpdateRequest();
        $dto->avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?' . str_repeat('a', 1000);

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
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

        $dto = new UpdateRequest();
        $dto->email = 'existing@example.com';

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

        $dto = new UpdateRequest();
        $dto->username = 'existing_username';

        $errors = $this->validator->validate($dto);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('déjà utilisé', $errors[0]->getMessage());

        $this->entityManager->remove($existingUser);
        $this->entityManager->flush();
    }

    // ========== Tests de la méthode update() ==========

    public function testUpdateEmailOnly(): void
    {
        $user = new User();
        $user->setEmail('old@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');

        $dto = new UpdateRequest();
        $dto->email = 'new@example.com';

        $dto->update($user);

        $this->assertEquals('new@example.com', $user->getEmail());
        $this->assertEquals('user123', $user->getUsername());
    }

    public function testUpdateUsernameOnly(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('oldusername');
        $user->setPassword('password');

        $dto = new UpdateRequest();
        $dto->username = 'newusername';

        $dto->update($user);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('newusername', $user->getUsername());
    }

    public function testUpdateAvatarUrlOnly(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');
        $user->setAvatarUrl('https://api.dicebear.com/9.x/avataaars/svg?seed=old');

        $dto = new UpdateRequest();
        $dto->avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?seed=new';

        $dto->update($user);

        $this->assertEquals('https://api.dicebear.com/9.x/avataaars/svg?seed=new', $user->getAvatarUrl());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('user123', $user->getUsername());
    }

    public function testUpdateAllFields(): void
    {
        $user = new User();
        $user->setEmail('old@example.com');
        $user->setUsername('olduser');
        $user->setPassword('password');
        $user->setAvatarUrl('https://api.dicebear.com/9.x/avataaars/svg?seed=old');

        $dto = new UpdateRequest();
        $dto->email = 'new@example.com';
        $dto->username = 'newuser';
        $dto->avatarUrl = 'https://api.dicebear.com/9.x/avataaars/svg?seed=new';

        $dto->update($user);

        $this->assertEquals('new@example.com', $user->getEmail());
        $this->assertEquals('newuser', $user->getUsername());
        $this->assertEquals('https://api.dicebear.com/9.x/avataaars/svg?seed=new', $user->getAvatarUrl());
    }

    public function testUpdateDoesNotChangeIfSameValue(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');

        $dto = new UpdateRequest();
        $dto->email = 'test@example.com';

        $dto->update($user);

        $this->assertEquals('test@example.com', $user->getEmail());
    }

    public function testUpdateWithNullValues(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');

        $dto = new UpdateRequest();

        $dto->update($user);

        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('user123', $user->getUsername());
    }

    public function testUpdateEmailConsentToFalse(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');

        $this->assertTrue($user->hasEmailConsent());

        $dto = new UpdateRequest();
        $dto->emailConsent = false;

        $dto->update($user);

        $this->assertFalse($user->hasEmailConsent());
    }

    public function testUpdateEmailConsentToTrue(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');
        $user->setEmailConsent(false);

        $dto = new UpdateRequest();
        $dto->emailConsent = true;

        $dto->update($user);

        $this->assertTrue($user->hasEmailConsent());
    }

    public function testUpdateNullEmailConsentLeavesItUnchanged(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('user123');
        $user->setPassword('password');
        $user->setEmailConsent(false);

        $dto = new UpdateRequest();

        $dto->update($user);

        $this->assertFalse($user->hasEmailConsent());
    }
}
