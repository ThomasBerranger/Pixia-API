<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the gameplay flows using the fixture game.
 */
class GameplayFlowTest extends WebTestCase
{
    public function testWinGameCreatesAchievement(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Register a new user
        $email = 'gameplay-' . uniqid() . '@test.com';
        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'password123',
            'username' => 'gameplay-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        // Get the fixture game (Matrix)
        $game = $em->getRepository(Game::class)->findOneBy(['solution' => 'Matrix']);
        self::assertNotNull($game, 'Fixture game should exist');

        // Submit correct answer
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => 'Matrix',
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($response);
        self::assertArrayHasKey('solution', $response);

        // Verify achievement was created
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $em->refresh($user);

        $achievement = $em->getRepository(Achievement::class)->findOneBy([
            'player' => $user,
            'game' => $game,
        ]);
        self::assertNotNull($achievement, 'Achievement should be created after winning');
    }

    public function testWrongAnswerReturnsFalse(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Register user
        $email = 'wrong-' . uniqid() . '@test.com';
        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => 'password123',
            'username' => 'wrong-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        // Get fixture game
        $game = $em->getRepository(Game::class)->findOneBy(['solution' => 'Matrix']);

        // Submit wrong answer
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => 'WrongAnswer',
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($response);
    }

    public function testUnauthenticatedCanSubmitAnswer(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $game = $em->getRepository(Game::class)->findOneBy(['solution' => 'Matrix']);

        // Submit without auth
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => 'Matrix',
        ]);

        // Should work but without creating achievement
        self::assertResponseIsSuccessful();
    }

    public function testGetDailyGameWithoutAuth(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $game = $em->getRepository(Game::class)->findOneBy(['solution' => 'Matrix']);

        $client->request('GET', '/games/daily', [
            'date' => $game->getDate()->format('Y-m-d'),
            'type' => $game->getType()->value,
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('game', $response);
        self::assertArrayNotHasKey('solution', $response['game']);
    }
}
