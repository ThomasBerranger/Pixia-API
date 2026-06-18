<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameStatus;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameFlowTest extends WebTestCase
{
    public function testDailyGameHidesSensitiveFieldsWithoutAuth(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();

        $client->jsonRequest('GET', '/games/daily?date=' . $game->getDate()->format('Y-m-d'));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('game', $data);
        self::assertArrayNotHasKey('solution', $data['game']);
        self::assertArrayNotHasKey('englishSolution', $data['game']);
        self::assertArrayNotHasKey('hint', $data['game']);
        self::assertArrayHasKey('type', $data['game']);
        self::assertArrayHasKey('date', $data['game']);
    }

    public function testDailyGameHidesSensitiveFieldsForUnansweredGame(): void
    {
        $client = static::createClient();
        $user = $this->getAdminUser();
        $game = $this->getTestGame();

        $client->loginUser($user);
        $client->jsonRequest('GET', '/games/daily?date=' . $game->getDate()->format('Y-m-d'));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayNotHasKey('solution', $data['game']);
        self::assertArrayNotHasKey('englishSolution', $data['game']);
    }

    public function testWrongAnswerReturnsFalseWithoutSolution(): void
    {
        $client = static::createClient();
        $gameId = $this->getTestGame()->getId()->toString();

        $client->jsonRequest('POST', "/games/{$gameId}/solution", ['answer' => 'Wrong answer here']);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertFalse($data);
    }

    public function testCorrectAnswerReturnsSolutionString(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();
        $gameId = $game->getId()->toString();

        $client->jsonRequest('POST', "/games/{$gameId}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsString($data);
        self::assertEquals($game->getSolution(), $data);
    }

    public function testHintEndpointRequiresAuthentication(): void
    {
        $client = static::createClient();
        $gameId = $this->getTestGame()->getId()->toString();

        $client->request('GET', "/games/{$gameId}/hint");

        self::assertResponseStatusCodeSame(401);
    }

    public function testHintEndpointReturnsHintString(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();
        $gameId = $game->getId()->toString();

        $client->loginUser($this->getTestUser());
        $client->jsonRequest('GET', "/games/{$gameId}/hint");

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertEquals($game->getHint(), $data);
    }

    public function testAdminNonStakeholderCannotViewGame(): void
    {
        $client = static::createClient();
        $gameId = $this->getTestGame()->getId()->toString();

        $client->loginUser($this->getAdminUser());
        $client->request('GET', "/admin/games/{$gameId}");

        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminCanViewAnyGame(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();
        $gameId = $game->getId()->toString();

        $client->loginUser($this->getSuperAdminUser());
        $client->jsonRequest('GET', "/admin/games/{$gameId}");

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('solution', $data);
        self::assertArrayHasKey('hint', $data);
        self::assertArrayHasKey('englishSolution', $data);
        self::assertArrayHasKey('author', $data);
    }

    // ========== Daily game data ==========

    public function testDailyGameExposesImageWhenApproved(): void
    {
        $client = static::createClient();
        $game = $this->getTodayGame();

        $client->jsonRequest('GET', '/games/daily?date=' . $game->getDate()->format('Y-m-d'));

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('image', $data['game']);
        self::assertNotNull($data['game']['image']);
    }

    // ========== Win flow E2E ==========

    public function testWinCreatesAchievement(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $game = $this->getTodayGame();

        $client->loginUser($user);
        $client->jsonRequest('POST', "/games/{$game->getId()->toString()}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $achievement = $this->getEntityManager()
            ->getRepository(Achievement::class)
            ->findOneBy(['player' => $user, 'game' => $game]);

        self::assertNotNull($achievement);
        self::assertSame($game->getDifficulty(), $achievement->getDifficulty());
    }

    public function testWinCreditsScoreAndTokens(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $game = $this->getTodayGame();

        $scoreBefore = $user->getScore();
        $tokensBefore = $user->getTokens();
        $expectedPoints = $game->getDifficulty()->getPoints(false);

        $client->loginUser($user);
        $client->jsonRequest('POST', "/games/{$game->getId()->toString()}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $this->getEntityManager()->refresh($user);

        self::assertSame($scoreBefore + $expectedPoints, $user->getScore());
        self::assertSame($tokensBefore + $expectedPoints, $user->getTokens());
    }

    public function testWinIncrementsAttempts(): void
    {
        $client = static::createClient();
        $game = $this->getTodayGame();
        $attemptsBefore = $game->getAttempts();

        $client->jsonRequest('POST', "/games/{$game->getId()->toString()}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $this->getEntityManager()->refresh($game);

        self::assertSame($attemptsBefore + 1, $game->getAttempts());
    }

    public function testWinIsNotReplayable(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $game = $this->getTodayGame();
        $userId = $user->getId();
        $gameId = $game->getId()->toString();
        $solution = $game->getSolution();

        $client->loginUser($user);

        // Première soumission : victoire
        $client->jsonRequest('POST', "/games/{$gameId}/solution", ['answer' => $solution]);
        self::assertResponseIsSuccessful();

        $em = $this->getEntityManager();
        $userAfterFirst = $em->getRepository(User::class)->find($userId);
        $scoreAfterFirstWin = $userAfterFirst->getScore();
        $tokensAfterFirstWin = $userAfterFirst->getTokens();

        // Deuxième soumission : ne doit rien créditer
        $client->jsonRequest('POST', "/games/{$gameId}/solution", ['answer' => $solution]);
        self::assertResponseIsSuccessful();

        $em->clear();
        $userAfterSecond = $em->getRepository(User::class)->find($userId);

        $achievements = $em->getRepository(Achievement::class)
            ->findBy(['player' => $userAfterSecond, 'game' => $em->getRepository(Game::class)->find($gameId)]);

        self::assertCount(1, $achievements);
        self::assertSame($scoreAfterFirstWin, $userAfterSecond->getScore(), 'Score should not increase on replay');
        self::assertSame($tokensAfterFirstWin, $userAfterSecond->getTokens(), 'Tokens should not increase on replay');
    }

    // ========== Anonymous win ==========

    public function testAnonymousCorrectAnswerDoesNotCreateAchievement(): void
    {
        $client = static::createClient();
        $game = $this->getTodayGame();

        $client->jsonRequest('POST', "/games/{$game->getId()->toString()}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $achievements = $this->getEntityManager()
            ->getRepository(Achievement::class)
            ->findBy(['game' => $game]);

        // Aucun achievement ne doit être créé pour un joueur anonyme
        self::assertCount(0, $achievements);
    }

    // ========== Token claim ==========

    public function testClaimTokensAddsTokensToPlayer(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();
        $tokensBefore = $user->getTokens();

        $client->loginUser($user);
        $client->jsonRequest('POST', '/me/tokens/claim');

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertArrayHasKey('addedTokens', $data);
        self::assertArrayHasKey('tokens', $data);
        self::assertSame(0, $data['addedTokens'] % 5);
        self::assertGreaterThan($tokensBefore, $data['tokens']);
    }

    public function testClaimTokensAdminRequiresAdminRole(): void
    {
        $client = static::createClient();
        $user = $this->getTestUser();

        $client->loginUser($user);
        $client->jsonRequest('POST', '/me/tokens/claim?isAdminClaim=true');

        self::assertResponseStatusCodeSame(403);
    }

    // ========== Helpers ==========

    private function getTestGame(): Game
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy([]);
    }

    private function getTodayGame(): Game
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy(['date' => new \DateTimeImmutable('today'), 'status' => GameStatus::APPROVED]);
    }

    private function getTestUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'test@test.com']);
    }

    private function getAdminUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'admin@test.com']);
    }

    private function getSuperAdminUser(): User
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(User::class)
            ->findOneBy(['email' => 'superadmin@test.com']);
    }

    private function getEntityManager(): \Doctrine\ORM\EntityManagerInterface
    {
        return self::getContainer()->get('doctrine')->getManager();
    }
}
