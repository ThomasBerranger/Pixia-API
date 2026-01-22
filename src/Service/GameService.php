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
     * @param list<array{month: string, difficulty: GameDifficulty, total: int, won: int, hinted: int}> $stats
     * @return array{
     *     total: array{total: int, won: int, hinted: int},
     *     byDifficulty: array<string, array{total: int, won: int, hinted: int}>,
     *     byMonth: array<string, array{total: int, won: int, hinted: int}>
     * }
     */
    public function formatUserGamesData(array $stats): array
    {
        $formattedStats = [
            'total' => ['total' => 0, 'won' => 0, 'hinted' => 0],
            'byDifficulty' => [],
            'byMonth' => [],
        ];

        foreach ($stats as $stat) {
            $month = $stat['month'];
            $difficulty = $stat['difficulty']->value;
            $total = (int)$stat['total'];
            $won = (int)$stat['won'];
            $hinted = (int)$stat['hinted'];

            $formattedStats['total']['total'] += $total;
            $formattedStats['total']['won'] += $won;
            $formattedStats['total']['hinted'] += $hinted;

            $formattedStats['byDifficulty'][$difficulty] ??= ['total' => 0, 'won' => 0, 'hinted' => 0];
            $formattedStats['byDifficulty'][$difficulty]['total'] += $total;
            $formattedStats['byDifficulty'][$difficulty]['won'] += $won;
            $formattedStats['byDifficulty'][$difficulty]['hinted'] += $hinted;

            $formattedStats['byMonth'][$month] ??= ['total' => 0, 'won' => 0, 'hinted' => 0];
            $formattedStats['byMonth'][$month]['total'] += $total;
            $formattedStats['byMonth'][$month]['won'] += $won;
            $formattedStats['byMonth'][$month]['hinted'] += $hinted;
        }

        return $formattedStats;
    }
}
