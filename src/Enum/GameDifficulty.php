<?php

declare(strict_types=1);

namespace App\Enum;

enum GameDifficulty: string
{
    case EASY = 'SIMPLE';
    case TRICKY = 'VICIEUX';
    case MEDIUM = 'TORDU';
    case HARD = 'DIABOLIQUE';
    case IMPOSSIBLE = 'IMPOSSIBLE';

    public function getPoints(bool $hinted): int
    {
        return $hinted ? match ($this) {
            self::EASY => 5,
            self::TRICKY => 10,
            self::MEDIUM => 15,
            self::HARD => 25,
            self::IMPOSSIBLE => 40,
        } : match ($this) {
            self::EASY => 10,
            self::TRICKY => 15,
            self::MEDIUM => 25,
            self::HARD => 50,
            self::IMPOSSIBLE => 80,
        };
    }

    /**
     * @return list<self>
     */
    public static function fromString(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $difficulties = [];

        foreach (explode(',', $value) as $item) {
            $difficulty = self::tryFrom($item);
            if ($difficulty !== null) {
                $difficulties[] = $difficulty;
            }
        }

        return $difficulties;
    }

    public function getOrder(): int
    {
        return match($this) {
            self::EASY => 1,
            self::TRICKY => 2,
            self::MEDIUM => 3,
            self::HARD => 4,
            self::IMPOSSIBLE => 5,
        };
    }
}
