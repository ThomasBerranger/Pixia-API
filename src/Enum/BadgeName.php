<?php

declare(strict_types=1);

namespace App\Enum;

enum BadgeName: string
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
    case SPONSOR = 'SPONSOR';
    case SPONSOR_5 = 'SPONSOR_5';
    case STREAK_10 = 'STREAK_10';
    case STREAK_30 = 'STREAK_30';
    case STREAK_90 = 'STREAK_90';
    case STREAK_200 = 'STREAK_200';

    public function label(): string
    {
        return match ($this) {
            self::CREATOR => 'Créateur',
            self::BETA_TESTER => 'Bêta-Testeur',
            self::WINS_1 => 'Débutant',
            self::WINS_10 => 'Cinéphile',
            self::WINS_50 => 'Expert',
            self::WINS_100 => 'Maître',
            self::WINS_300 => 'Légendaire',
            self::TOP_10_REACHED => 'Top 10',
            self::GAME_SUGGESTION => 'Génie',
            self::BUG_DETECTED => 'Débugger',
            self::SPONSOR => 'Ambassadeur',
            self::SPONSOR_5 => 'Influenceur',
            self::STREAK_10 => 'En feu',
            self::STREAK_30 => 'Inarrêtable',
            self::STREAK_90 => 'Astre flamboyant',
            self::STREAK_200 => 'Perfectionniste',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::CREATOR => '🎨',
            self::BETA_TESTER => '⏱️',
            self::WINS_1 => '🐣',
            self::WINS_10 => '🎬',
            self::WINS_50 => '🎓',
            self::WINS_100 => '💯',
            self::WINS_300 => '💎',
            self::TOP_10_REACHED => '🔝',
            self::GAME_SUGGESTION => '💡',
            self::BUG_DETECTED => '🪲',
            self::SPONSOR => '🤝',
            self::SPONSOR_5 => '📣',
            self::STREAK_10 => '🔥',
            self::STREAK_30 => '⚡',
            self::STREAK_90 => '☄️',
            self::STREAK_200 => '💫',
        };
    }
}
