<?php

namespace App\Enum;

enum GameDifficulty: string
{
    case EASY = 'SIMPLE';
    case MEDIUM = 'TORDU';
    case HARD = 'DIABOLIQUE';

    public function getPoints(): int
    {
        return match($this) {
            self::EASY => 10,
            self::MEDIUM => 25,
            self::HARD => 50,
        };
    }
}
