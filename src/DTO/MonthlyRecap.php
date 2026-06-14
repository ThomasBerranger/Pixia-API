<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\BadgeName;
use App\Enum\RecapTier;

final readonly class MonthlyRecap
{
    /**
     * @param list<BadgeName>                                        $badges
     * @param list<array{label: string, guessers: int, attempts: int}> $createdPixias
     */
    public function __construct(
        public string   $monthName,
        public string   $monthLabel,
        public string   $quote,
        public ?int     $rank,
        public RecapTier $tier,
        public ?string $championUsername,
        public int     $championGap,
        public int     $won,
        public int     $points,
        public array   $badges,
        public array   $createdPixias,
        public int     $tokens,
        public int     $tokensMissing,
        public int     $tokensPercent,
    ) {
    }
}
