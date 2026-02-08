<?php

declare(strict_types=1);

namespace App\Tests\Unit\Serialization;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\GameDifficulty;
use App\Enum\GameType;
use App\Serializer\GameNormalizer;
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

    public function testShowGroupStripsSensitiveFieldsWithoutFlags(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
        ]);

        $data = json_decode($json, true);

        self::assertEquals(['id', 'type', 'date', 'difficulty', 'image'], array_keys($data));
    }

    public function testShowGroupIncludesSolutionWithFlag(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_SOLUTION => true,
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('solution', $data);
        self::assertArrayHasKey('englishSolution', $data);
        self::assertArrayNotHasKey('hint', $data);
    }

    public function testShowGroupIncludesHintWithFlag(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_HINT => true,
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('hint', $data);
        self::assertArrayNotHasKey('solution', $data);
    }

    public function testShowGroupIncludesAllWithBothFlags(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_SOLUTION => true,
            GameNormalizer::SHOW_HINT => true,
        ]);

        $data = json_decode($json, true);

        self::assertEquals(
            ['id', 'type', 'solution', 'hint', 'date', 'difficulty', 'image', 'englishSolution'],
            array_keys($data)
        );
    }

    public function testShowAdminGroup(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show:admin'],
            GameNormalizer::SHOW_SOLUTION => true,
            GameNormalizer::SHOW_HINT => true,
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
            'groups' => ['game:index:admin'],
            GameNormalizer::SHOW_SOLUTION => true,
            GameNormalizer::SHOW_HINT => true,
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
            'groups' => ['game:show'],
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
