<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TestFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setEmail('test@test.com')
            ->setUsername('test-user')
            ->setPassword('hashed');

        $manager->persist($user);

        $admin = new User();
        $admin->setEmail('admin@test.com')
            ->setUsername('admin-user')
            ->setPassword('hashed')
            ->setRoles([UserRole::ADMIN->value]);

        $manager->persist($admin);

        $game = new Game();
        $game->setSolution('Matrix')
            ->setEnglishSolution('Matrix')
            ->setHint('Un film de science fiction')
            ->setDate(new \DateTimeImmutable())
            ->setType(GameType::MULTIVERS)
            ->setDifficulty(GameDifficulty::EASY)
            ->setAuthor($user)
            ->setImageUrl('')
            ->setImage('test.jpg');

        $manager->persist($game);

        $manager->flush();
    }
}
