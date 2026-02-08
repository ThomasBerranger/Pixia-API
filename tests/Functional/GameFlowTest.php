<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Game;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class GameFlowTest extends WebTestCase
{
    public function testDailyGameHidesSensitiveFieldsWithoutAuth(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();

        $client->jsonRequest('GET', '/games/daily?date=' . $game->getDate()->format('Y-m-d') . '&type=multivers');

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
        $user = $this->getTestUser();
        $game = $this->getTestGame();

        $client->loginUser($user);
        $client->jsonRequest('GET', '/games/daily?date=' . $game->getDate()->format('Y-m-d') . '&type=multivers');

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

    public function testCorrectAnswerReturnsGameWithSolution(): void
    {
        $client = static::createClient();
        $game = $this->getTestGame();
        $gameId = $game->getId()->toString();

        $client->jsonRequest('POST', "/games/{$gameId}/solution", ['answer' => $game->getSolution()]);

        self::assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('solution', $data);
        self::assertEquals($game->getSolution(), $data['solution']);
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

    private function getTestGame(): Game
    {
        return self::getContainer()
            ->get('doctrine')
            ->getRepository(Game::class)
            ->findOneBy([]);
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
}
