<?php

declare(strict_types=1);

namespace App\Enum;

enum Badge: string
{
    case CREATOR = 'Créateur';
    case BETA_TESTER = 'Bêta-Testeur';
    case WINS_1 = 'Débutant';
    case WINS_10 = 'Cinéphile';
    case WINS_50 = 'Expert';
    case WINS_100 = 'Maître';
    case WINS_300 = 'Légendaire';
    case TOP_10_REACHED = 'Top 10';
    case STREAK_10 = 'En Feu';
    case STREAK_20 = 'Inarrêtable';
    case STREAK_30 = 'Perfectionniste';
}
