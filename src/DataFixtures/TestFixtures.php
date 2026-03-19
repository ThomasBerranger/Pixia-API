<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Achievement;
use App\Entity\Game;
use App\Entity\MonthlyLeaderboard;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameStatus;
use App\Enum\GameType;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TestFixtures extends Fixture
{
    private ObjectManager $manager;

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;

        $users = $this->createUsers();
        $games = $this->createGames($users);
        $this->createMonthlyLeaderboard($users['user']);

        $manager->flush();

        // Achievements en dernier : le listener AchievementListener trigger BadgeService
        // qui flush en interne. Re-fetch les entités entre chaque pour éviter les doublons de badges.
        $userId = $users['user']->getId();
        $gameIds = [$games['approvedYesterday']->getId(), $games['approvedOld']->getId()];

        foreach ($gameIds as $gameId) {
            /** @var User $player */
            $player = $manager->getRepository(User::class)->find($userId);
            /** @var Game $game */
            $game = $manager->getRepository(Game::class)->find($gameId);

            $this->createAchievement($player, $game);
        }
    }

    /**
     * @return array{user: User, admin: User, superAdmin: User, banned: User}
     */
    private function createUsers(): array
    {
        $user = $this->makeUser('test@test.com', 'test-user', 5000, 150);
        $admin = $this->makeUser('admin@test.com', 'admin-user', 0, 0, [UserRole::ADMIN->value]);
        $superAdmin = $this->makeUser('superadmin@test.com', 'super-admin-user', 0, 0, [UserRole::SUPER_ADMIN->value]);
        $banned = $this->makeUser('banned@test.com', 'banned-user', 0, 0, [UserRole::BANNED->value]);

        return ['user' => $user, 'admin' => $admin, 'superAdmin' => $superAdmin, 'banned' => $banned];
    }

    /**
     * @param list<string> $roles
     */
    private function makeUser(string $email, string $username, int $score, int $tokens, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setUsername($username)
            ->setPassword('hashed')
            ->setScore($score)
            ->setRoles($roles)
            ->addTokens($tokens);

        $this->manager->persist($user);

        return $user;
    }

    /**
     * @param array{user: User, admin: User, superAdmin: User, banned: User} $users
     * @return array{approved: Game, approvedYesterday: Game, approvedOld: Game, draft: Game, pending: Game, rejected: Game}
     */
    private function createGames(array $users): array
    {
        $today = new \DateTimeImmutable('today');

        $approved = $this->makeGame(
            'Matrix et Inception', 'Matrix and Inception', 'Un rêve dans la matrice',
            $today, GameDifficulty::EASY, GameStatus::APPROVED, $users['user'], 'today.jpg'
        );

        $approvedYesterday = $this->makeGame(
            'Titanic et Avatar', 'Titanic and Avatar', 'Un voyage bleu profond',
            $today->modify('-1 day'), GameDifficulty::TRICKY, GameStatus::APPROVED, $users['user'], 'yesterday.jpg'
        );

        $approvedOld = $this->makeGame(
            'Le Roi Lion et Babe', 'The Lion King and Babe', 'Un roi de la ferme',
            $today->modify('-7 days'), GameDifficulty::HARD, GameStatus::APPROVED, $users['user'], 'old.jpg'
        );

        $draft = $this->makeGame(
            'Alien et Predator', 'Alien and Predator', 'Chasseurs de l\'espace',
            $today->modify('+2 weeks'), GameDifficulty::MEDIUM, GameStatus::DRAFT, $users['user']
        );

        $pending = $this->makeGame(
            'Star Wars et Dune', 'Star Wars and Dune', 'Sable et étoiles',
            $today->modify('+2 weeks'), GameDifficulty::IMPOSSIBLE, GameStatus::PENDING, $users['user']
        );
        $pending->setSupervisor($users['admin']);

        $rejected = $this->makeGame(
            'Cars et Fast', 'Cars and Fast', 'Voitures rapides',
            $today->modify('+2 weeks'), GameDifficulty::EASY, GameStatus::REJECTED, $users['user']
        );
        $rejected->setSupervisor($users['admin']);

        return [
            'approved' => $approved,
            'approvedYesterday' => $approvedYesterday,
            'approvedOld' => $approvedOld,
            'draft' => $draft,
            'pending' => $pending,
            'rejected' => $rejected,
        ];
    }

    private function makeGame(
        string $solution,
        string $englishSolution,
        string $hint,
        \DateTimeImmutable $date,
        GameDifficulty $difficulty,
        GameStatus $status,
        User $author,
        ?string $image = null,
    ): Game {
        $game = new Game();
        $game->setSolution($solution)
            ->setEnglishSolution($englishSolution)
            ->setHint($hint)
            ->setDate($date)
            ->setType(GameType::MULTIVERS)
            ->setDifficulty($difficulty)
            ->setStatus($status)
            ->setAuthor($author)
            ->setImage($image);

        $this->manager->persist($game);

        return $game;
    }

    private function createAchievement(User $player, Game $game): void
    {
        $achievement = new Achievement();
        $achievement->setPlayer($player)
            ->setGame($game)
            ->setDate($game->getDate())
            ->setDifficulty($game->getDifficulty());

        $this->manager->persist($achievement);
        $this->manager->flush();
        $this->manager->clear();
    }

    private function createMonthlyLeaderboard(User $player): void
    {
        $leaderboard = new MonthlyLeaderboard();
        $leaderboard->setPlayer($player)
            ->setMonth(new \DateTimeImmutable('first day of this month'))
            ->setScore(150);

        $this->manager->persist($leaderboard);
    }
}
