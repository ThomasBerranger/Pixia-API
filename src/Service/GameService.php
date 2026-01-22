<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\GameDifficulty;

final readonly class GameService
{
    public function __construct()
    {
    }

    /**
     * @param list<array{month: string, difficulty: GameDifficulty, total: int, won: int}> $stats
     * @return array{
     *     total: array{total: int, won: int},
     *     byDifficulty: array<string, array{total: int, won: int}>,
     *     byMonth: array<string, array{total: int, won: int}>
     * }
     */
    public function formatUserGamesData(array $stats): array
    {
        $formattedStats = [
            'total' => ['total' => 0, 'won' => 0],
            'byDifficulty' => [],
            'byMonth' => [],
        ];

        foreach ($stats as $stat) {
            $month = $stat['month'];
            $difficulty = $stat['difficulty']->value;
            $total = (int)$stat['total'];
            $won = (int)$stat['won'];

            $formattedStats['total']['total'] += $total;
            $formattedStats['total']['won'] += $won;

            $formattedStats['byDifficulty'][$difficulty] ??= ['total' => 0, 'won' => 0];
            $formattedStats['byDifficulty'][$difficulty]['total'] += $total;
            $formattedStats['byDifficulty'][$difficulty]['won'] += $won;

            $formattedStats['byMonth'][$month] ??= ['total' => 0, 'won' => 0];
            $formattedStats['byMonth'][$month]['total'] += $total;
            $formattedStats['byMonth'][$month]['won'] += $won;
        }

        uksort($formattedStats['byDifficulty'], fn (string $a, string $b) =>
            GameDifficulty::from($a)->getOrder() <=> GameDifficulty::from($b)->getOrder()
        );

        return $formattedStats;
    }
}
