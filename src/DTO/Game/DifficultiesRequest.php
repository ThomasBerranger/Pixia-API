<?php

declare(strict_types=1);

namespace App\DTO\Game;

use App\Enum\GameDifficulty;

final readonly class DifficultiesRequest
{
    public function __construct(
        public ?string $difficulties = null,
    ) {
    }

    /**
     * @return list<GameDifficulty>
     */
    public function getDifficulties(): array
    {
        if ($this->difficulties === null || $this->difficulties === '') {
            return [];
        }

        $difficulties = [];

        foreach (explode(',', $this->difficulties) as $value) {
            $difficulty = GameDifficulty::tryFrom($value);
            if ($difficulty !== null) {
                $difficulties[] = $difficulty;
            }
        }

        return $difficulties;
    }
}
