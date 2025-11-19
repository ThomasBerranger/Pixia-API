<?php

declare(strict_types=1);

namespace App\Enum;

enum GameDifficulty: string
{
    case EASY = 'SIMPLE';
    case MEDIUM = 'TORDU';
    case HARD = 'DIABOLIQUE';

    public function getPoints(bool $hinted): int
    {
        return $hinted ? match ($this) {
            self::EASY => 5,
            self::MEDIUM => 15,
            self::HARD => 25,
        } : match ($this) {
            self::EASY => 10,
            self::MEDIUM => 25,
            self::HARD => 50,
        };
    }
}
