<?php

declare(strict_types=1);

namespace App\DTO\API;

readonly class MovieAnalysisFilm
{
    public function __construct(
        public string $titre,
        public float $notorietyScore,
    ) {
    }
}
