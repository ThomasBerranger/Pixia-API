<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the complete gameplay flows.
 * Uses real JWT authentication to properly test the stateless firewall.
 */
class GameplayFlowTest extends WebTestCase
{
    // ========== Win Game Flow ==========

    public function testWinGameFlow(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create and register a new user via API (this creates a proper user with hashed password)
        $email = 'gametest-' . uniqid() . '@test.com';
        $password = 'testpassword123';
        $username = 'gametest-' . uniqid();

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => $username,
        ]);
        self::assertResponseStatusCodeSame(201);
        $registerResponse = json_decode($client->getResponse()->getContent(), true);
        $token = $registerResponse['token'];

        // Get user ID
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $userId = $user->getId()->toString();
        $initialScore = $user->getScore();

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);
        $gameId = $game->getId()->toString();
        $gameSolution = $game->getSolution();

        // Step 1: Submit wrong answer (with auth token)
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => 'Wrong Answer',
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($response); // Wrong answer returns false

        // Step 2: Submit correct answer
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($response); // Correct answer returns game data
        self::assertArrayHasKey('solution', $response);

        // Step 3: Verify achievement was created (use fresh query)
        $em->clear();
        $achievementCount = $em->createQuery(
            'SELECT COUNT(a) FROM App\Entity\Achievement a WHERE a.player = :playerId AND a.game = :gameId'
        )
            ->setParameter('playerId', $userId)
            ->setParameter('gameId', $gameId)
            ->getSingleScalarResult();
        self::assertEquals(1, $achievementCount, 'Achievement should be created after winning');

        // Step 4: Verify score was updated
        $updatedUser = $em->getRepository(User::class)->find($userId);
        self::assertGreaterThan($initialScore, $updatedUser->getScore(), 'Score should increase after winning');
    }

    // ========== Already Won Flow (No Double Points) ==========

    public function testAlreadyWonDoesNotGiveDoublePoints(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create user via API
        $email = 'doubletest-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'doubletest-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $userId = $user->getId()->toString();

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);
        $gameId = $game->getId()->toString();
        $gameSolution = $game->getSolution();

        // First win
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        // Get score after first win
        $em->clear();
        $userAfterFirstWin = $em->getRepository(User::class)->find($userId);
        $scoreAfterFirstWin = $userAfterFirstWin->getScore();

        // Second submission with correct answer
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        // Verify score did NOT increase
        $em->clear();
        $userAfterSecondWin = $em->getRepository(User::class)->find($userId);
        self::assertSame(
            $scoreAfterFirstWin,
            $userAfterSecondWin->getScore(),
            'Score should NOT increase on second correct answer'
        );

        // Verify only ONE achievement exists
        $achievementCount = $em->createQuery(
            'SELECT COUNT(a) FROM App\Entity\Achievement a WHERE a.player = :playerId AND a.game = :gameId'
        )
            ->setParameter('playerId', $userId)
            ->setParameter('gameId', $gameId)
            ->getSingleScalarResult();
        self::assertEquals(1, $achievementCount, 'Only one achievement should exist');
    }

    // ========== Win with Hint Flow ==========

    public function testWinWithHintAppliesPenalty(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create two users via API
        $emailWithHint = 'hintuser-' . uniqid() . '@test.com';
        $emailWithoutHint = 'nohintuser-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        // Register user with hint
        $client->jsonRequest('POST', '/register', [
            'email' => $emailWithHint,
            'password' => $password,
            'username' => 'hintuser-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $tokenWithHint = json_decode($client->getResponse()->getContent(), true)['token'];

        $userWithHint = $em->getRepository(User::class)->findOneBy(['email' => $emailWithHint]);
        $userWithHintId = $userWithHint->getId()->toString();

        // Register user without hint
        $client->jsonRequest('POST', '/register', [
            'email' => $emailWithoutHint,
            'password' => $password,
            'username' => 'nohintuser-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $tokenWithoutHint = json_decode($client->getResponse()->getContent(), true)['token'];

        $userWithoutHint = $em->getRepository(User::class)->findOneBy(['email' => $emailWithoutHint]);
        $userWithoutHintId = $userWithoutHint->getId()->toString();

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);
        $gameId = $game->getId()->toString();
        $gameSolution = $game->getSolution();

        // User 1: Request hint then win
        $client->request('GET', '/games/' . $gameId . '/hint', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $tokenWithHint,
        ]);
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenWithHint]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $userWithHintUpdated = $em->getRepository(User::class)->find($userWithHintId);
        $scoreWithHint = $userWithHintUpdated->getScore();

        // User 2: Win without hint
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $tokenWithoutHint]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $userWithoutHintUpdated = $em->getRepository(User::class)->find($userWithoutHintId);
        $scoreWithoutHint = $userWithoutHintUpdated->getScore();

        // User without hint should have more points (10 vs 5 for EASY)
        self::assertLessThan(
            $scoreWithoutHint,
            $scoreWithHint,
            'User with hint should receive fewer points'
        );
    }

    // ========== Get Daily Game Flow ==========

    public function testGetDailyGameFlow(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        $game = $em->getRepository(Game::class)->findOneBy([]);
        $today = $game->getDate()->format('Y-m-d');

        // Get daily game (unauthenticated)
        $client->request('GET', '/games/daily', [
            'date' => $today,
            'type' => $game->getType()->value,
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('game', $response);
        self::assertArrayNotHasKey('solution', $response['game'], 'Solution should not be exposed before winning');
    }

    public function testGetDailyGameWithAchievementShowsSolution(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create user via API
        $email = 'dailytest-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'dailytest-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $game = $em->getRepository(Game::class)->findOneBy([]);
        $gameId = $game->getId()->toString();
        $gameSolution = $game->getSolution();
        $gameDate = $game->getDate()->format('Y-m-d');
        $gameType = $game->getType()->value;

        // First, win the game
        $client->jsonRequest('POST', '/games/' . $gameId . '/solution', [
            'answer' => $gameSolution,
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        // Now get daily game - should include solution
        $client->request('GET', '/games/daily', [
            'date' => $gameDate,
            'type' => $gameType,
        ], [], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('achievement', $response);
        self::assertNotNull($response['achievement'], 'Achievement should be present');
        self::assertArrayHasKey('solution', $response['game'], 'Solution should be exposed after winning');
    }
}
