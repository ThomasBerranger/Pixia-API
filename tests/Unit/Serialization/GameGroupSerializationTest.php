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

    public function testShowGroupWithoutContextHidesSensitiveFields(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
        ]);

        $data = json_decode($json, true);

        self::assertArrayNotHasKey('solution', $data);
        self::assertArrayNotHasKey('englishSolution', $data);
        self::assertArrayNotHasKey('hint', $data);
        self::assertArrayHasKey('type', $data);
        self::assertArrayHasKey('date', $data);
        self::assertArrayHasKey('difficulty', $data);
        self::assertArrayHasKey('image', $data);
    }

    public function testShowGroupWithSolutionContextExposesSolution(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_SOLUTION => true,
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('solution', $data);
        self::assertArrayHasKey('englishSolution', $data);
        self::assertEquals('Film 1', $data['solution']);
        self::assertEquals('Film 1 EN', $data['englishSolution']);
        self::assertArrayNotHasKey('hint', $data);
    }

    public function testShowGroupWithHintContextExposesHint(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_HINT => true,
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('hint', $data);
        self::assertEquals('Test hint', $data['hint']);
        self::assertArrayNotHasKey('solution', $data);
        self::assertArrayNotHasKey('englishSolution', $data);
    }

    public function testShowGroupWithBothContextsExposesAll(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show'],
            GameNormalizer::SHOW_SOLUTION => true,
            GameNormalizer::SHOW_HINT => true,
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('solution', $data);
        self::assertArrayHasKey('englishSolution', $data);
        self::assertArrayHasKey('hint', $data);
        self::assertArrayHasKey('type', $data);
        self::assertArrayHasKey('date', $data);
    }

    public function testShowAdminGroupExposesAdminFields(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:show:admin'],
        ]);

        $data = json_decode($json, true);

        // Admin-specific fields are present
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('author', $data);
        self::assertEquals(['username'], array_keys($data['author']));

        // Common fields
        self::assertArrayHasKey('type', $data);
        self::assertArrayHasKey('date', $data);
        self::assertArrayHasKey('difficulty', $data);
        self::assertArrayHasKey('image', $data);

        // Sensitive fields stripped by normalizer (no authenticated stakeholder)
        self::assertArrayNotHasKey('solution', $data);
        self::assertArrayNotHasKey('hint', $data);
        self::assertArrayNotHasKey('englishSolution', $data);
    }

    public function testIndexAdminGroupExposesAdminFields(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:index:admin'],
        ]);

        $data = json_decode($json, true);

        // Admin-specific fields
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('author', $data);
        self::assertEquals(['username', 'avatarUrl'], array_keys($data['author']));

        // Common fields
        self::assertArrayHasKey('type', $data);
        self::assertArrayHasKey('date', $data);
        self::assertArrayHasKey('difficulty', $data);
        self::assertArrayHasKey('image', $data);

        // Sensitive fields stripped by normalizer (no authenticated stakeholder)
        self::assertArrayNotHasKey('solution', $data);
        self::assertArrayNotHasKey('hint', $data);
    }

    public function testIndexGroupDoesNotExposeSensitiveFields(): void
    {
        $game = $this->createTestGame();

        $json = $this->serializer->serialize($game, 'json', [
            'groups' => ['game:index'],
        ]);

        $data = json_decode($json, true);

        self::assertArrayHasKey('type', $data);
        self::assertArrayHasKey('date', $data);
        self::assertArrayHasKey('difficulty', $data);
        self::assertArrayHasKey('image', $data);
        self::assertArrayNotHasKey('solution', $data);
        self::assertArrayNotHasKey('hint', $data);
        self::assertArrayNotHasKey('englishSolution', $data);
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
