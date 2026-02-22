<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class UserGameCreationTest extends WebTestCase
{
    private function getTestUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);
    }

    public function testCreateGameRequiresAuthentication(): void
    {
        $client = static::createClient();

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Une fusion entre Matrix et Le Seigneur des Anneaux'
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    // Tests retirés : testCreateGameWithValidIdeaReturnsCreatedGame, testCreateGamePersistsToDatabase,
    // testCreateGameAssignsDateAtLeastOneWeekAhead, testCreateGameSetsCurrentUserAsAuthor
    // → dépendent d'appels API externes (Claude), à remplacer par des tests avec mock

    public function testCreateGameWithEmptyIdeaReturnsValidationError(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => ''
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateGameWithTooShortIdeaReturnsValidationError(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Court' // Moins de 10 caractères
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateGameWithTooLongIdeaReturnsValidationError(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => str_repeat('a', 501) // Plus de 500 caractères
        ]);

        self::assertResponseStatusCodeSame(422);
    }

}
