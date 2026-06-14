<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\RecapTier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecapTierTest extends TestCase
{
    #[DataProvider('fromRankProvider')]
    public function testFromRank(?int $rank, int $points, RecapTier $expected): void
    {
        $this->assertSame($expected, RecapTier::fromRank($rank, $points));
    }

    /**
     * @return iterable<string, array{?int, int, RecapTier}>
     */
    public static function fromRankProvider(): iterable
    {
        // No points (or no rank) → always INACTIVE, even if ranked.
        yield 'zero points, no rank' => [null, 0, RecapTier::INACTIVE];
        yield 'zero points but ranked first' => [1, 0, RecapTier::INACTIVE];
        yield 'points but no rank' => [null, 50, RecapTier::INACTIVE];

        // Rank tiers (with points).
        yield 'rank 1 → champion' => [1, 100, RecapTier::CHAMPION];
        yield 'rank 2 → podium' => [2, 100, RecapTier::PODIUM];
        yield 'rank 3 → podium (upper bound)' => [3, 100, RecapTier::PODIUM];
        yield 'rank 4 → top 10 (lower bound)' => [4, 100, RecapTier::TOP_10];
        yield 'rank 10 → top 10 (upper bound)' => [10, 100, RecapTier::TOP_10];
        yield 'rank 11 → top 50 (lower bound)' => [11, 100, RecapTier::TOP_50];
        yield 'rank 50 → top 50 (upper bound)' => [50, 100, RecapTier::TOP_50];
        yield 'rank 51 → top 100 (lower bound)' => [51, 100, RecapTier::TOP_100];
        yield 'rank 100 → top 100 (upper bound)' => [100, 100, RecapTier::TOP_100];
        yield 'rank 101 → ranked' => [101, 100, RecapTier::RANKED];
        yield 'rank 5000 → ranked' => [5000, 5, RecapTier::RANKED];
    }

    public function testPunchlineIsNeverEmpty(): void
    {
        foreach (RecapTier::cases() as $tier) {
            $this->assertNotSame('', $tier->punchline(), "Punchline must not be empty for {$tier->name}");
        }
    }
}
