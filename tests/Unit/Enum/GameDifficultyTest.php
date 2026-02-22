<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\GameDifficulty;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GameDifficultyTest extends TestCase
{
    // ========== Tests getPoints() without hint ==========

    #[DataProvider('pointsWithoutHintProvider')]
    public function testGetPointsWithoutHint(GameDifficulty $difficulty, int $expectedPoints): void
    {
        $this->assertSame($expectedPoints, $difficulty->getPoints(hinted: false));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function pointsWithoutHintProvider(): iterable
    {
        yield 'EASY' => [GameDifficulty::EASY, 10];
        yield 'TRICKY' => [GameDifficulty::TRICKY, 15];
        yield 'MEDIUM' => [GameDifficulty::MEDIUM, 25];
        yield 'HARD' => [GameDifficulty::HARD, 50];
        yield 'IMPOSSIBLE' => [GameDifficulty::IMPOSSIBLE, 80];
    }

    // ========== Tests getPoints() with hint ==========

    #[DataProvider('pointsWithHintProvider')]
    public function testGetPointsWithHint(GameDifficulty $difficulty, int $expectedPoints): void
    {
        $this->assertSame($expectedPoints, $difficulty->getPoints(hinted: true));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function pointsWithHintProvider(): iterable
    {
        yield 'EASY' => [GameDifficulty::EASY, 5];
        yield 'TRICKY' => [GameDifficulty::TRICKY, 10];
        yield 'MEDIUM' => [GameDifficulty::MEDIUM, 15];
        yield 'HARD' => [GameDifficulty::HARD, 25];
        yield 'IMPOSSIBLE' => [GameDifficulty::IMPOSSIBLE, 40];
    }

    // ========== Tests getOrder() ==========

    #[DataProvider('orderProvider')]
    public function testGetOrder(GameDifficulty $difficulty, int $expectedOrder): void
    {
        $this->assertSame($expectedOrder, $difficulty->getOrder());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function orderProvider(): iterable
    {
        yield 'EASY' => [GameDifficulty::EASY, 1];
        yield 'TRICKY' => [GameDifficulty::TRICKY, 2];
        yield 'MEDIUM' => [GameDifficulty::MEDIUM, 3];
        yield 'HARD' => [GameDifficulty::HARD, 4];
        yield 'IMPOSSIBLE' => [GameDifficulty::IMPOSSIBLE, 5];
    }

    // ========== Tests enum values ==========

    #[DataProvider('enumValuesProvider')]
    public function testEnumValues(GameDifficulty $difficulty, string $expectedValue): void
    {
        $this->assertSame($expectedValue, $difficulty->value);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function enumValuesProvider(): iterable
    {
        yield 'EASY' => [GameDifficulty::EASY, 'SIMPLE'];
        yield 'TRICKY' => [GameDifficulty::TRICKY, 'VICIEUX'];
        yield 'MEDIUM' => [GameDifficulty::MEDIUM, 'TORDU'];
        yield 'HARD' => [GameDifficulty::HARD, 'DIABOLIQUE'];
        yield 'IMPOSSIBLE' => [GameDifficulty::IMPOSSIBLE, 'IMPOSSIBLE'];
    }

    // ========== Tests hint reduces points ==========

    public function testHintReducesPointsForAllDifficulties(): void
    {
        foreach (GameDifficulty::cases() as $difficulty) {
            $withoutHint = $difficulty->getPoints(hinted: false);
            $withHint = $difficulty->getPoints(hinted: true);

            $this->assertGreaterThan(
                $withHint,
                $withoutHint,
                "Points without hint should be greater than with hint for {$difficulty->name}"
            );
        }
    }

    // ========== Tests order is sequential ==========

    public function testOrderIsSequential(): void
    {
        $orders = array_map(
            fn (GameDifficulty $d) => $d->getOrder(),
            GameDifficulty::cases()
        );

        sort($orders);

        $this->assertSame([1, 2, 3, 4, 5], $orders);
    }

    public function testDifficultiesCanBeSortedByOrder(): void
    {
        $difficulties = GameDifficulty::cases();

        usort($difficulties, fn (GameDifficulty $a, GameDifficulty $b) => $a->getOrder() <=> $b->getOrder());

        $this->assertSame(GameDifficulty::EASY, $difficulties[0]);
        $this->assertSame(GameDifficulty::TRICKY, $difficulties[1]);
        $this->assertSame(GameDifficulty::MEDIUM, $difficulties[2]);
        $this->assertSame(GameDifficulty::HARD, $difficulties[3]);
        $this->assertSame(GameDifficulty::IMPOSSIBLE, $difficulties[4]);
    }
}
