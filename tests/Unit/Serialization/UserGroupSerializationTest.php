<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Achievement;
use App\Entity\Badge;
use App\Entity\Contribution;
use App\Entity\Game;
use App\Entity\MonthlyLeaderboard;
use App\Entity\User;
use App\Enum\BadgeName;
use App\Enum\ContributionStatus;
use App\Enum\ContributionType;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class UserGroupSerializationTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testMeGroup(): void
    {
        $user = $this->createUserWithAllRelations();

        $data = $this->serialize($user, ['user:me']);

        self::assertEquals(
            ['email', 'score', 'username', 'badges', 'contributions', 'avatarUrl', 'slug', 'monthlyLeaderboards', 'rank', 'tokens', 'emailConsent'],
            array_keys($data)
        );

        self::assertEquals(['name'], array_keys($data['badges'][0]));

        self::assertEquals(
            ['title', 'type', 'status', 'createdAt'],
            array_keys($data['contributions'][0])
        );

        self::assertEquals(['score', 'month'], array_keys($data['monthlyLeaderboards'][0]));

        $achievement = $this->createAchievementWithGame();

        $data = $this->serialize($achievement, ['user:me']);

        self::assertEquals(['date', 'game'], array_keys($data));

        // Solution is stripped by GameNormalizer because no authenticated stakeholder during this test
        self::assertArrayNotHasKey('solution', $data['game']);
    }

    public function testShowGroup(): void
    {
        $user = $this->createUserWithAchievements();

        $data = $this->serialize($user, ['user:show']);

        self::assertEquals(['score', 'username', 'badges', 'avatarUrl', 'achievements'], array_keys($data));

        self::assertEquals(['name'], array_keys($data['badges'][0]));

        self::assertEquals(['game'], array_keys($data['achievements'][0]));

        self::assertEquals(['type', 'date', 'image'], array_keys($data['achievements'][0]['game']));
    }

    public function testIndexGroup(): void
    {
        $user = $this->createUserWithMonthlyLeaderboards();
        $user->setRank(1);

        $data = $this->serialize($user, ['user:index']);

        self::assertEquals(
            ['score', 'username', 'badges', 'avatarUrl', 'slug', 'streaks', 'monthlyLeaderboards', 'rank'],
            array_keys($data)
        );

        self::assertEquals(['name'], array_keys($data['badges'][0]));

        self::assertEquals(['current', 'best'], array_keys($data['streaks']));

        self::assertEquals(['score', 'month'], array_keys($data['monthlyLeaderboards'][0]));
    }

    public function testPasswordNeverExposed(): void
    {
        $user = $this->createBaseUser();

        foreach (['user:me', 'user:show', 'user:index'] as $group) {
            $data = $this->serialize($user, [$group]);

            self::assertArrayNotHasKey('password', $data, "Password exposed in group $group");
        }
    }

    public function testEmailAbsentFromPublicGroups(): void
    {
        $user = $this->createBaseUser();

        foreach (['user:show', 'user:index'] as $group) {
            $data = $this->serialize($user, [$group]);

            self::assertArrayNotHasKey('email', $data, "Email exposed in group $group");
        }
    }

    /**
     * @param array<string> $groups
     * @return array<string, mixed>
     */
    private function serialize(User|Achievement $entity, array $groups): array
    {
        $json = $this->serializer->serialize($entity, 'json', ['groups' => $groups]);

        return json_decode($json, true);
    }

    private function createBaseUser(): User
    {
        $user = new User();
        $user->setEmail('test@test.com');
        $user->setUsername('testuser');
        $user->setPassword('password');
        $user->setScore(100);
        $user->setAvatarUrl('https://example.com/avatar.png');
        $user->setStreaks(['current' => 5, 'best' => 10]);

        return $user;
    }

    private function createUserWithAllRelations(): User
    {
        $user = $this->createBaseUser();

        $user->addBadge($this->createBadge($user, BadgeName::SPONSOR));
        $user->addContribution($this->createContribution());
        $user->addMonthlyLeaderboard($this->createMonthlyLeaderboard($user));

        return $user;
    }

    private function createUserWithAchievements(): User
    {
        $user = $this->createBaseUser();

        $user->addBadge($this->createBadge($user, BadgeName::CREATOR));
        $user->addAchievement($this->createAchievement($user));

        return $user;
    }

    private function createUserWithMonthlyLeaderboards(): User
    {
        $user = $this->createBaseUser();

        $user->addBadge($this->createBadge($user, BadgeName::STREAK_10));
        $user->addMonthlyLeaderboard($this->createMonthlyLeaderboard($user));

        return $user;
    }

    private function createBadge(User $player, BadgeName $name): Badge
    {
        $badge = new Badge($name);
        $badge->setPlayer($player);

        return $badge;
    }

    private function createContribution(): Contribution
    {
        $contribution = new Contribution();
        $contribution->setTitle('Test Contribution');
        $contribution->setDescription('Description');
        $contribution->setType(ContributionType::GAME_SUGGESTION);
        $contribution->setStatus(ContributionStatus::PENDING);

        return $contribution;
    }

    private function createMonthlyLeaderboard(User $player): MonthlyLeaderboard
    {
        $monthlyLeaderboard = new MonthlyLeaderboard();
        $monthlyLeaderboard->setPlayer($player);
        $monthlyLeaderboard->setScore(50);
        $monthlyLeaderboard->setMonth(new \DateTimeImmutable('2025-01-01'));

        return $monthlyLeaderboard;
    }

    private function createAchievement(User $player): Achievement
    {
        $game = $this->createGame($player);

        $achievement = new Achievement();
        $achievement->setDate(new \DateTimeImmutable());
        $achievement->setDifficulty(GameDifficulty::EASY);
        $achievement->setPlayer($player);
        $achievement->setGame($game);

        return $achievement;
    }

    private function createGame(User $author): Game
    {
        $game = new Game();
        $game->setDate(new \DateTimeImmutable());
        $game->setType(GameType::MULTIVERS);
        $game->setDifficulty(GameDifficulty::EASY);
        $game->setImage('test.jpg');
        $game->setSolution('Film');
        $game->setEnglishSolution('Film EN');
        $game->setHint('Hint');
        $game->setAuthor($author);

        return $game;
    }

    private function createAchievementWithGame(): Achievement
    {
        $author = $this->createBaseUser();
        $author->setEmail('author@test.com');
        $author->setUsername('author');

        return $this->createAchievement($author);
    }
}
