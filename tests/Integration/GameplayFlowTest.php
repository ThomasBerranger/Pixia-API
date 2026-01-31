<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the complete gameplay flows.
 */
class GameplayFlowTest extends WebTestCase
{
    // ========== Win Game Flow ==========

    public function testWinGameFlow(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create a fresh user for this test
        $user = $this->createTestUser($em);
        $initialScore = $user->getScore();

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);

        // Login user
        $client->loginUser($user);

        // Step 1: Submit wrong answer
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => 'Wrong Answer',
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertFalse($response); // Wrong answer returns false

        // Step 2: Submit correct answer
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        self::assertIsArray($response); // Correct answer returns game data
        self::assertArrayHasKey('solution', $response);

        // Step 3: Verify achievement was created
        $em->clear();
        $achievement = $em->getRepository(Achievement::class)->findOneBy([
            'player' => $user,
            'game' => $game,
        ]);
        self::assertNotNull($achievement, 'Achievement should be created after winning');

        // Step 4: Verify score was updated
        $em->clear();
        $updatedUser = $em->getRepository(User::class)->find($user->getId());
        self::assertGreaterThan($initialScore, $updatedUser->getScore(), 'Score should increase after winning');
    }

    // ========== Already Won Flow (No Double Points) ==========

    public function testAlreadyWonDoesNotGiveDoublePoints(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create a fresh user
        $user = $this->createTestUser($em);

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);

        $client->loginUser($user);

        // First win
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        // Get score after first win
        $em->clear();
        $userAfterFirstWin = $em->getRepository(User::class)->find($user->getId());
        $scoreAfterFirstWin = $userAfterFirstWin->getScore();

        // Second submission with correct answer
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        // Verify score did NOT increase
        $em->clear();
        $userAfterSecondWin = $em->getRepository(User::class)->find($user->getId());
        self::assertSame(
            $scoreAfterFirstWin,
            $userAfterSecondWin->getScore(),
            'Score should NOT increase on second correct answer'
        );

        // Verify only ONE achievement exists
        $achievements = $em->getRepository(Achievement::class)->findBy([
            'player' => $user,
            'game' => $game,
        ]);
        self::assertCount(1, $achievements, 'Only one achievement should exist');
    }

    // ========== Win with Hint Flow ==========

    public function testWinWithHintAppliesPenalty(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create two fresh users
        $userWithHint = $this->createTestUser($em, 'hint');
        $userWithoutHint = $this->createTestUser($em, 'nohint');

        // Get the test game
        $game = $em->getRepository(Game::class)->findOneBy([]);

        // User 1: Request hint then win
        $client->loginUser($userWithHint);
        $client->request('GET', '/games/' . $game->getId() . '/hint');
        self::assertResponseIsSuccessful();

        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $userWithHintUpdated = $em->getRepository(User::class)->find($userWithHint->getId());
        $scoreWithHint = $userWithHintUpdated->getScore();

        // User 2: Win without hint
        $client->loginUser($userWithoutHint);
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $userWithoutHintUpdated = $em->getRepository(User::class)->find($userWithoutHint->getId());
        $scoreWithoutHint = $userWithoutHintUpdated->getScore();

        // User without hint should have more points
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
        $today = (new \DateTimeImmutable())->format('Y-m-d');

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

        $user = $this->createTestUser($em);
        $game = $em->getRepository(Game::class)->findOneBy([]);
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        $client->loginUser($user);

        // First, win the game
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        // Now get daily game - should include solution
        $client->request('GET', '/games/daily', [
            'date' => $today,
            'type' => $game->getType()->value,
        ]);

        self::assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('achievement', $response);
        self::assertNotNull($response['achievement'], 'Achievement should be present');
        self::assertArrayHasKey('solution', $response['game'], 'Solution should be exposed after winning');
    }

    // ========== Helper Methods ==========

    private function createTestUser($em, string $suffix = ''): User
    {
        $uniqueId = uniqid() . $suffix;
        $user = new User();
        $user->setEmail("testuser-{$uniqueId}@test.com")
            ->setUsername("testuser-{$uniqueId}")
            ->setPassword('hashed');

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
