<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
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

    public function testCreateGameWithValidIdeaReturnsCreatedGame(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Une fusion épique entre Matrix et Le Seigneur des Anneaux'
        ]);

        self::assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        // Vérifier la structure de la réponse
        $this->assertArrayHasKey('game', $responseData);
        $this->assertArrayHasKey('popularityScore', $responseData);
        $this->assertArrayHasKey('prompt', $responseData);

        // Vérifier les données du game
        $game = $responseData['game'];
        $this->assertArrayHasKey('id', $game);
        $this->assertArrayHasKey('solution', $game);
        $this->assertArrayHasKey('englishSolution', $game);
        $this->assertArrayHasKey('hint', $game);
        $this->assertArrayHasKey('date', $game);

        // Vérifier le score de popularité (fictif pour l'instant)
        $this->assertEquals(75, $responseData['popularityScore']);
    }

    public function testCreateGamePersistsToDatabase(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $doctrine = self::getContainer()->get('doctrine');
        $gameRepository = $doctrine->getRepository(Game::class);

        // Compter les games avant création
        $countBefore = count($gameRepository->findBy(['author' => $user]));

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Une fusion entre Inception et Interstellar'
        ]);

        self::assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $responseData['game']['id'];

        // Vérifier que le game existe en BDD
        $doctrine->getManager()->clear(); // Clear cache
        $game = $gameRepository->find($gameId);

        $this->assertNotNull($game);
        $this->assertEquals($user->getId(), $game->getAuthor()->getId());
        $this->assertEquals(GameStatus::DRAFT, $game->getStatus());

        // Vérifier qu'il y a bien un game de plus
        $countAfter = count($gameRepository->findBy(['author' => $user]));
        $this->assertEquals($countBefore + 1, $countAfter);
    }

    public function testCreateGameAssignsDateAtLeastOneWeekAhead(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Une fusion entre Avatar et Titanic'
        ]);

        self::assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $gameDate = new \DateTimeImmutable($responseData['game']['date']);
        $oneWeekFromNow = (new \DateTimeImmutable('+1 week'))->setTime(0, 0);

        // Vérifier que la date est au minimum dans 1 semaine
        $this->assertGreaterThanOrEqual(
            $oneWeekFromNow->getTimestamp(),
            $gameDate->getTimestamp(),
            'La date du game doit être au minimum dans 1 semaine'
        );
    }

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

    public function testCreateGameSetsCurrentUserAsAuthor(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $client->loginUser($user);

        $client->jsonRequest('POST', '/me/games', [
            'idea' => 'Une fusion entre Star Wars et Harry Potter'
        ]);

        self::assertResponseStatusCodeSame(201);

        $responseData = json_decode($client->getResponse()->getContent(), true);
        $gameId = $responseData['game']['id'];

        $doctrine = self::getContainer()->get('doctrine');
        $doctrine->getManager()->clear();

        $game = $doctrine->getRepository(Game::class)->find($gameId);

        $this->assertEquals($user->getId(), $game->getAuthor()->getId());
    }
}
