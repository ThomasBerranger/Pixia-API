<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Serializer\SerializerInterface;

class GameGroupSerializationTest extends KernelTestCase
{
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->serializer = self::getContainer()->get(SerializerInterface::class);
    }

    public function testShowGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(['id', 'type', 'date', 'difficulty', 'image'], array_keys($data));
    }

    public function testSolutionGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:solution']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(['solution'], array_keys($data));
    }

    public function testHintGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:hint']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(['hint'], array_keys($data));
    }

    public function testShowAdminGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show:admin']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(
            ['id', 'type', 'solution', 'hint', 'date', 'difficulty', 'author', 'image', 'englishSolution'],
            array_keys($data)
        );

        self::assertEquals(['username'], array_keys($data['author']));
    }

    public function testIndexAdminGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:index:admin']
        ]);

        $data = json_decode($json, true);

        self::assertEquals(
            ['id', 'type', 'solution', 'hint', 'date', 'difficulty', 'author', 'image'],
            array_keys($data)
        );

        self::assertEquals(['email'], array_keys($data['author']));
    }

    public function testNormalizerTransformsImagePath(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show']
        ]);

        $data = json_decode($json, true);

        self::assertStringContainsString('/', $data['image']);
        self::assertStringContainsString('test.jpg', $data['image']);
    }

    private function createTestGame(): Game
    {
        $author = new User();
        $author->setEmail('author@test.com');
        $author->setUsername('author');

        $game = new Game();
        $game->setDate(new \DateTimeImmutable('2025-01-01'));
        $game->setType(GameType::MULTIVERS);
        $game->setDifficulty(GameDifficulty::MEDIUM);
        $game->setImage('test.jpg');
        $game->setSolution('Film 1');
        $game->setEnglishSolution('Film 1 EN');
        $game->setHint('Test hint');
        $game->setAuthor($author);

        return $game;
    }
}
