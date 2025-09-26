<?php

namespace App\Enum;

enum GameDifficulty: string
{
    case EASY = 'EASY';
    case MEDIUM = 'MEDIUM';
    case HARD = 'HARD';

    public function getPoints(): int
    {
        return match($this) {
            self::EASY => 10,
            self::MEDIUM => 25,
            self::HARD => 50,
        };
    }
}
