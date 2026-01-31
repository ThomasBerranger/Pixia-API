<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Badge;
use App\Entity\Game;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests the badge attribution flows.
 * Uses real JWT authentication to properly test the stateless firewall.
 */
class BadgeFlowTest extends WebTestCase
{
    // ========== First Win Badge Flow ==========

    public function testFirstWinGrantsWins1Badge(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Register a user via API
        $email = 'badgetest-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'badgetest-' . uniqid(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        // Get the user from database
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $userId = $user->getId()->toString();

        // Get the fixture author for game creation
        $fixtureAuthor = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);

        // Create a game for this test
        $game = $this->createTestGame($em, $fixtureAuthor);

        // Verify user has no WINS_1 badge initially
        $initialBadges = $em->getRepository(Badge::class)->findBy(['player' => $user]);
        $hasWins1Initially = $this->userHasBadge($initialBadges, BadgeName::WINS_1);
        self::assertFalse($hasWins1Initially, 'User should not have WINS_1 badge initially');

        // Win the game
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        // Verify WINS_1 badge was granted
        $em->clear();
        $updatedBadges = $em->createQuery(
            'SELECT b FROM App\Entity\Badge b WHERE b.player = :userId'
        )->setParameter('userId', $userId)->getResult();
        $hasWins1Now = $this->userHasBadge($updatedBadges, BadgeName::WINS_1);
        self::assertTrue($hasWins1Now, 'User should have WINS_1 badge after first win');
    }

    // ========== Sponsor Reward Flow ==========

    public function testSponsorReceivesRewardAfter10Wins(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Get the fixture user as sponsor (test@test.com already exists)
        $sponsor = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $sponsorId = $sponsor->getId()->toString();
        $sponsorInitialScore = $sponsor->getScore();

        // Register sponsored user via API with sponsor
        $email = 'sponsored-' . uniqid() . '@test.com';
        $password = 'testpassword123';

        $client->jsonRequest('POST', '/register', [
            'email' => $email,
            'password' => $password,
            'username' => 'sponsored-' . uniqid(),
            'sponsor' => $sponsor->getSlug(),
        ]);
        self::assertResponseStatusCodeSame(201);
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        // Create 10 games and win them all
        for ($i = 1; $i <= 10; $i++) {
            $game = $this->createTestGame($em, $sponsor, "Solution{$i}", $i);

            $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
                'answer' => $game->getSolution(),
            ], ['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);
            self::assertResponseIsSuccessful();
        }

        // Verify sponsor received points
        $em->clear();
        $updatedSponsor = $em->getRepository(User::class)->find($sponsorId);
        self::assertGreaterThan(
            $sponsorInitialScore,
            $updatedSponsor->getScore(),
            'Sponsor should receive points after sponsored user wins 10 games'
        );

        // Verify sponsor has SPONSOR badge
        $sponsorBadges = $em->createQuery(
            'SELECT b FROM App\Entity\Badge b WHERE b.player = :sponsorId'
        )->setParameter('sponsorId', $sponsorId)->getResult();
        $hasSponsorBadge = $this->userHasBadge($sponsorBadges, BadgeName::SPONSOR);
        self::assertTrue($hasSponsorBadge, 'Sponsor should have SPONSOR badge');
    }

    // ========== Helper Methods ==========

    private function createTestGame($em, User $author, string $solution = 'TestSolution', int $dayOffset = 0): Game
    {
        $game = new Game();
        $game->setSolution($solution)
            ->setEnglishSolution($solution)
            ->setHint('Test hint')
            ->setDate((new \DateTimeImmutable())->modify("+{$dayOffset} days"))
            ->setType(GameType::MULTIVERS)
            ->setDifficulty(GameDifficulty::EASY)
            ->setAuthor($author)
            ->setImageUrl('')
            ->setImage('test.jpg');

        $em->persist($game);
        $em->flush();

        return $game;
    }

    private function userHasBadge(array $badges, BadgeName $badgeName): bool
    {
        foreach ($badges as $badge) {
            if ($badge->getName() === $badgeName) {
                return true;
            }
        }
        return false;
    }
}
