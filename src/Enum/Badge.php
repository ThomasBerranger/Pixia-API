<?php

declare(strict_types=1);

namespace App\Enum;

enum Badge: string
{
    case CREATOR = 'CREATOR';
    case BETA_TESTER = 'BETA_TESTER';
    case WINS_1 = 'WINS_1';
    case WINS_10 = 'WINS_10';
    case WINS_50 = 'WINS_50';
    case WINS_100 = 'WINS_100';
    case WINS_300 = 'WINS_300';
    case TOP_10_REACHED = 'TOP_10_REACHED';
    case GAME_SUGGESTION = 'GAME_SUGGESTION';
    case BUG_DETECTED = 'BUG_DETECTED';
    case STREAK_10 = 'STREAK_10';
    case STREAK_20 = 'STREAK_20';
    case STREAK_30 = 'STREAK_30';
}
