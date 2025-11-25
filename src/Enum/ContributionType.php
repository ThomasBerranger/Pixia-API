<?php

declare(strict_types=1);

namespace App\Enum;

enum ContributionType: string
{
    case GAME_SUGGESTION = 'GAME_SUGGESTION';
    case BUG_DETECTED = 'BUG_DETECTED';
}
