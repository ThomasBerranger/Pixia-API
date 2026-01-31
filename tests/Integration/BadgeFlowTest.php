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
 */
class BadgeFlowTest extends WebTestCase
{
    // ========== First Win Badge Flow ==========

    public function testFirstWinGrantsWins1Badge(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create a fresh user without any badges
        $user = $this->createTestUser($em);

        // Create a game for this test
        $game = $this->createTestGame($em, $user);

        $client->loginUser($user);

        // Verify user has no WINS_1 badge initially
        $initialBadges = $em->getRepository(Badge::class)->findBy(['player' => $user]);
        $hasWins1Initially = $this->userHasBadge($initialBadges, BadgeName::WINS_1);
        self::assertFalse($hasWins1Initially, 'User should not have WINS_1 badge initially');

        // Win the game
        $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
            'answer' => $game->getSolution(),
        ]);
        self::assertResponseIsSuccessful();

        // Verify WINS_1 badge was granted
        $em->clear();
        $updatedBadges = $em->getRepository(Badge::class)->findBy(['player' => $user]);
        $hasWins1Now = $this->userHasBadge($updatedBadges, BadgeName::WINS_1);
        self::assertTrue($hasWins1Now, 'User should have WINS_1 badge after first win');
    }

    // ========== Sponsor Reward Flow ==========

    public function testSponsorReceivesRewardAfter10Wins(): void
    {
        $client = static::createClient();
        $em = self::getContainer()->get('doctrine')->getManager();

        // Create sponsor
        $sponsor = $this->createTestUser($em, 'sponsor');
        $sponsorInitialScore = $sponsor->getScore();

        // Create sponsored user with the sponsor
        $sponsoredUser = $this->createTestUser($em, 'sponsored');
        $sponsoredUser->setSponsor($sponsor);
        $em->flush();

        $client->loginUser($sponsoredUser);

        // Create 10 games and win them all
        for ($i = 1; $i <= 10; $i++) {
            $game = $this->createTestGame($em, $sponsor, "Solution{$i}", $i);

            $client->jsonRequest('POST', '/games/' . $game->getId() . '/solution', [
                'answer' => $game->getSolution(),
            ]);
            self::assertResponseIsSuccessful();
        }

        // Verify sponsor received points
        $em->clear();
        $updatedSponsor = $em->getRepository(User::class)->find($sponsor->getId());
        self::assertGreaterThan(
            $sponsorInitialScore,
            $updatedSponsor->getScore(),
            'Sponsor should receive points after sponsored user wins 10 games'
        );

        // Verify sponsor has SPONSOR badge
        $sponsorBadges = $em->getRepository(Badge::class)->findBy(['player' => $sponsor]);
        $hasSponsorBadge = $this->userHasBadge($sponsorBadges, BadgeName::SPONSOR);
        self::assertTrue($hasSponsorBadge, 'Sponsor should have SPONSOR badge');
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
