<?php

declare(strict_types=1);

namespace App\Tests\Unit\Deserialization\Authentication;

use App\DTO\Authentication\GoogleAuthRequest;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class GoogleAuthRequestTest extends KernelTestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = self::getContainer()->get(ValidatorInterface::class);
    }

    // ========== Tests de validation complète ==========

    public function testValidDataWithAccessToken(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'ya29.a0AfH6SMBx...'];

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    public function testValidDataWithAccessTokenAndSponsor(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'ya29.a0AfH6SMBx...'];
        $dto->sponsor = 'my-sponsor';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);
    }

    // ========== Tests googleAccessToken ==========

    public function testGoogleAccessTokenCannotBeEmpty(): void
    {
        $dto = new GoogleAuthRequest();
        // @phpstan-ignore assign.propertyType
        $dto->googleAccessToken = [];

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);

        // Vérifie qu'il y a une erreur sur le champ manquant
        $this->assertStringContainsString('missing', strtolower($errors[0]->getMessage()));
    }

    public function testGoogleAccessTokenMissingAccessTokenKey(): void
    {
        $dto = new GoogleAuthRequest();
        // @phpstan-ignore assign.propertyType
        $dto->googleAccessToken = ['wrong_key' => 'value'];

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testGoogleAccessTokenAccessTokenCannotBeBlank(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => ''];

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testGoogleAccessTokenAccessTokenMustBeString(): void
    {
        $dto = new GoogleAuthRequest();
        // @phpstan-ignore-next-line - Test volontaire d'un type incorrect
        $dto->googleAccessToken = ['access_token' => 123];

        $errors = $this->validator->validate($dto);
        $this->assertGreaterThan(0, $errors);
    }

    public function testGoogleAccessTokenWithValidStructure(): void
    {
        $validTokens = [
            ['access_token' => 'ya29.short'],
            ['access_token' => 'ya29.a0AfH6SMBxVeryLongTokenString123456789'],
            ['access_token' => 'token123'],
        ];

        foreach ($validTokens as $token) {
            $dto = new GoogleAuthRequest();
            $dto->googleAccessToken = $token;

            $errors = $this->validator->validate($dto);

            $tokenErrors = array_filter(
                iterator_to_array($errors),
                fn ($error) => $error->getPropertyPath() === 'googleAccessToken'
            );

            $this->assertCount(0, $tokenErrors, "Token structure should be valid");
        }
    }

    // ========== Tests sponsor (même validation que RegisterRequest) ==========

    public function testSponsorIsOptional(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'ya29.token'];
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
            $dto = new GoogleAuthRequest();
            $dto->googleAccessToken = ['access_token' => 'ya29.token'];
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
            $dto = new GoogleAuthRequest();
            $dto->googleAccessToken = ['access_token' => 'ya29.token'];
            $dto->sponsor = $sponsor;

            $errors = $this->validator->validate($dto);
            $this->assertGreaterThan(0, $errors, "Sponsor '{$sponsor}' ne devrait pas être valide");
        }
    }

    public function testSponsorMinLength(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'ya29.token'];
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
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'ya29.token'];
        $dto->sponsor = str_repeat('a', 256); // > 255

        $errors = $this->validator->validate($dto);

        $sponsorErrors = array_filter(
            iterator_to_array($errors),
            fn ($error) => $error->getPropertyPath() === 'sponsor'
        );

        $this->assertGreaterThan(0, $sponsorErrors);
    }

    // ========== Tests de la méthode getAccessToken() ==========

    public function testGetAccessTokenReturnsToken(): void
    {
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = ['access_token' => 'my-secret-token'];

        $token = $dto->getAccessToken();

        $this->assertEquals('my-secret-token', $token);
    }

    public function testGetAccessTokenReturnsCorrectValue(): void
    {
        $dto = new GoogleAuthRequest();
        $expectedToken = 'ya29.a0AfH6SMBxVeryLongGoogleToken123456789';
        $dto->googleAccessToken = ['access_token' => $expectedToken];

        $actualToken = $dto->getAccessToken();

        $this->assertSame($expectedToken, $actualToken);
    }

    public function testGetAccessTokenWithDifferentTokens(): void
    {
        $tokens = [
            'token1',
            'ya29.short',
            'very-long-token-string-12345',
        ];

        foreach ($tokens as $expectedToken) {
            $dto = new GoogleAuthRequest();
            $dto->googleAccessToken = ['access_token' => $expectedToken];

            $this->assertEquals($expectedToken, $dto->getAccessToken());
        }
    }

    // ========== Tests d'intégration ==========

    public function testDTOStructureMatchesExpectedFormat(): void
    {
        // Simule ce que le frontend envoie
        $dto = new GoogleAuthRequest();
        $dto->googleAccessToken = [
            'access_token' => 'ya29.a0AfH6SMBx...',
        ];
        $dto->sponsor = 'my-friend';

        $errors = $this->validator->validate($dto);
        $this->assertCount(0, $errors);

        // Vérifie que getAccessToken() fonctionne
        $this->assertNotEmpty($dto->getAccessToken());
        $this->assertEquals('ya29.a0AfH6SMBx...', $dto->getAccessToken());
    }
}
