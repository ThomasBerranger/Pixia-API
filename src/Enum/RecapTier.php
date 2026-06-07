<?php

declare(strict_types=1);

namespace App\Enum;

enum RecapTier: string
{
    case CHAMPION = 'CHAMPION';
    case PODIUM = 'PODIUM';
    case TOP_10 = 'TOP_10';
    case TOP_50 = 'TOP_50';
    case TOP_100 = 'TOP_100';
    case RANKED = 'RANKED';
    case INACTIVE = 'INACTIVE';

    public static function fromRank(?int $rank, int $points): self
    {
        if ($points === 0 || $rank === null) {
            return self::INACTIVE;
        }

        return match (true) {
            $rank === 1 => self::CHAMPION,
            $rank <= 3 => self::PODIUM,
            $rank <= 10 => self::TOP_10,
            $rank <= 50 => self::TOP_50,
            $rank <= 100 => self::TOP_100,
            default => self::RANKED,
        };
    }

    public function punchline(): string
    {
        return match ($this) {
            self::CHAMPION => "C'est toi le champion. 👑",
            self::PODIUM => 'Tu es si proche du trône de fer que tu peux le toucher. ⚔️',
            self::TOP_10 => 'Top 10 — les projecteurs sont sur toi. 🎥',
            self::TOP_50 => "Top 50 : tu n'es plus un figurant. 🎭",
            self::TOP_100 => 'Tu entres dans le top 100, ça se fête. 🍿',
            self::RANKED => 'Tu chauffes, le mois prochain tu exploses. 💥',
            self::INACTIVE => "Silence sur le plateau… à toi de relancer l'action. 🎬",
        };
    }
}
